<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Service;

use OCA\TimeGuardian\Db\Correction;
use OCA\TimeGuardian\Db\CorrectionMapper;
use OCA\TimeGuardian\Db\Entry;
use OCA\TimeGuardian\Db\EntryMapper;
use OCA\TimeGuardian\Domain\Dates;
use OCA\TimeGuardian\Domain\Report;
use OCA\TimeGuardian\Domain\TgException;
use OCA\TimeGuardian\Domain\Tz;
use OCA\TimeGuardian\Domain\Validator;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;

/** Manual bookings: create, edit, delete (own, 7 days), and applying approved corrections. Everything is audited. */
class EntryService {
	public function __construct(
		private EntryMapper $entries,
		private CorrectionMapper $corrections,
		private ConfigService $config,
		private UserConfService $userConf,
		private ViolationService $violations,
		private EffectsService $effects,
		private AuditService $audit,
		private AccessService $access,
		private ITimeFactory $time,
	) {
	}

	private function snapshot(Entry $e): array {
		return [
			'day' => $e->getDay(),
			'start' => Dates::iso((int)$e->getStartTs()),
			'end' => Dates::isoOrNull($e->getEndTs()),
			'pauseMin' => $e->getPauseMin(),
			'project' => $e->getProject(),
			'note' => $e->getNote(),
			'source' => $e->getSource(),
		];
	}

	private function view(Entry $e): array {
		$now = $this->time->getTime();
		$tz = $this->config->tz();
		return Report::entryView($e->toRow(), $now, $tz, Tz::day($now, $tz), true);
	}

	/** @return list<array> */
	public function list(string $uid, string $from, string $to): array {
		$now = $this->time->getTime();
		$tz = $this->config->tz();
		$today = Tz::day($now, $tz);
		return array_map(fn (Entry $e) => Report::entryView($e->toRow(), $now, $tz, $today, true), $this->entries->findRange($uid, $from, $to));
	}

	/** @throws TgException */
	private function own(string $uid, int $id): Entry {
		try {
			$e = $this->entries->find($id);
		} catch (DoesNotExistException) {
			throw new TgException('not_found');
		}
		if ($e->getUid() !== $uid) {
			throw new TgException('not_found');
		}
		return $e;
	}

	private function assertEditable(Entry $e): void {
		$now = $this->time->getTime();
		if ($e->getDay() < Dates::add(Tz::day($now, $this->config->tz()), -Validator::EDIT_DAYS)) {
			throw new TgException('locked');
		}
	}

	private function after(string $uid, array $days): array {
		$days = array_values(array_unique($days));
		sort($days);
		$r = $this->violations->refresh($uid, $days[0], end($days));
		$this->effects->violations($uid, $r['new']);
		return $r;
	}

	private function result(string $uid, Entry $e, array $r): array {
		$lang = $this->userConf->langOf($uid);
		return [
			'entry' => $this->view($e),
			'newViolations' => array_map(fn ($v) => Report::violationView($v, $lang), $r['new']),
			'violations' => array_map(fn ($v) => Report::violationView($v, $lang), array_values(array_filter($r['all'], fn ($v) => $v['day'] === $e->getDay()))),
		];
	}

	public function create(string $uid, array $in): array {
		$this->access->assertMember($uid);
		$now = $this->time->getTime();
		$tz = $this->config->tz();
		$v = Validator::entry($in, $now, $tz, $this->config->projects());
		if (!$v['ok']) {
			throw new TgException('validation', $v['errors']);
		}
		$x = $v['entry'];
		if ($this->entries->findOverlap($uid, $x['startTs'], $x['endTs']) !== null) {
			throw new TgException('overlap');
		}
		$e = new Entry();
		$e->setUid($uid);
		$e->setDay(Tz::day($x['startTs'], $tz));
		$e->setStartTs($x['startTs']);
		$e->setEndTs($x['endTs']);
		$e->setPauseMin($x['pauseMin']);
		$e->setProject($x['project']);
		$e->setNote($x['note']);
		$e->setSource('manual');
		$e->setCreatedAt($now);
		$e->setUpdatedAt($now);
		/** @var Entry $e */
		$e = $this->entries->insert($e);
		$this->audit->log($uid, $uid, 'entry.create', 'entry', (int)$e->getId(), null, $this->snapshot($e));
		$r = $this->after($uid, [$e->getDay(), Tz::day($x['endTs'], $tz)]);
		$this->effects->entrySaved($e->toRow());
		return $this->result($uid, $e, $r);
	}

	public function update(string $uid, int $id, array $in): array {
		$e = $this->own($uid, $id);
		$this->assertEditable($e);
		if ($e->getEndTs() === null) {
			throw new TgException('invalid_transition');
		}
		$now = $this->time->getTime();
		$tz = $this->config->tz();
		$v = Validator::entryUpdate($in, ['day' => $e->getDay(), 'startTs' => (int)$e->getStartTs(), 'endTs' => $e->getEndTs(), 'pauseMin' => $e->getPauseMin(), 'project' => $e->getProject(), 'note' => $e->getNote()], $now, $tz, $this->config->projects());
		if (!$v['ok']) {
			throw new TgException('validation', $v['errors']);
		}
		$x = $v['entry'];
		if ($this->entries->findOverlap($uid, $x['startTs'], $x['endTs'], $id) !== null) {
			throw new TgException('overlap');
		}
		$before = $this->snapshot($e);
		$oldDay = $e->getDay();
		$e->setDay(Tz::day($x['startTs'], $tz));
		$e->setStartTs($x['startTs']);
		$e->setEndTs($x['endTs']);
		$e->setPauseMin($x['pauseMin']);
		$e->setProject($x['project']);
		$e->setNote($x['note']);
		$e->setUpdatedAt($now);
		$this->entries->update($e);
		$this->audit->log($uid, $uid, 'entry.update', 'entry', $id, $before, $this->snapshot($e));
		$r = $this->after($uid, [$oldDay, $e->getDay(), Tz::day($x['endTs'], $tz)]);
		$this->effects->entrySaved($e->toRow());
		return $this->result($uid, $e, $r);
	}

	public function delete(string $uid, int $id): void {
		$e = $this->own($uid, $id);
		$this->assertEditable($e);
		$before = $this->snapshot($e);
		$day = $e->getDay();
		$this->entries->delete($e);
		$this->corrections->deleteForEntry($id);
		$this->audit->log($uid, $uid, 'entry.delete', 'entry', $id, $before, null);
		$this->after($uid, [$day]);
		$this->effects->entryRemoved($uid, $id);
	}

	/** applies an approved correction to its entry (no 7 day limit: a manager decided) */
	public function applyCorrection(Correction $c, string $actor): ?Entry {
		try {
			$e = $this->entries->find((int)$c->getEntryId());
		} catch (DoesNotExistException) {
			return null;
		}
		$tz = $this->config->tz();
		$before = $this->snapshot($e);
		$oldDay = $e->getDay();
		if ($c->getProposedStart() !== null) {
			$e->setStartTs((int)$c->getProposedStart());
			$e->setDay(Tz::day((int)$c->getProposedStart(), $tz));
		}
		if ($c->getProposedEnd() !== null) {
			$e->setEndTs((int)$c->getProposedEnd());
		}
		if ($c->getProposedPause() !== null) {
			$e->setPauseMin((int)$c->getProposedPause());
		}
		$e->setSource('correction');
		$e->setUpdatedAt($this->time->getTime());
		$this->entries->update($e);
		$this->audit->log($actor, $e->getUid(), 'entry.correction', 'entry', (int)$e->getId(), $before, $this->snapshot($e));
		$days = [$oldDay, $e->getDay()];
		if ($e->getEndTs() !== null) {
			$days[] = Tz::day((int)$e->getEndTs(), $tz);
		}
		$this->after($e->getUid(), $days);
		$this->effects->entrySaved($e->toRow());
		return $e;
	}
}
