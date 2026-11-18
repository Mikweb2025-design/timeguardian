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
use OCA\TimeGuardian\Domain\Sanitize;
use OCA\TimeGuardian\Domain\TgException;
use OCA\TimeGuardian\Domain\Tz;
use OCA\TimeGuardian\Domain\Validator;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;

/** Correction requests for bookings older than the edit window (or any booking): the manager approves or rejects. */
class CorrectionService {
	public function __construct(
		private CorrectionMapper $corrections,
		private EntryMapper $entries,
		private EntryService $entryService,
		private ConfigService $config,
		private AccessService $access,
		private AuditService $audit,
		private EffectsService $effects,
		private ITimeFactory $time,
	) {
	}

	private function entryOrNull(int $id): ?Entry {
		try {
			return $this->entries->find($id);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	private function block(?int $start, ?int $end, ?int $pause, \DateTimeZone $tz): ?array {
		if ($start === null && $end === null && $pause === null) {
			return null;
		}
		return [
			'start' => Dates::isoOrNull($start),
			'end' => Dates::isoOrNull($end),
			'startHM' => $start === null ? null : Tz::hm($start, $tz),
			'endHM' => $end === null ? null : Tz::hm($end, $tz),
			'pauseMin' => $pause,
		];
	}

	public function view(Correction $c): array {
		$tz = $this->config->tz();
		$e = $this->entryOrNull((int)$c->getEntryId());
		return [
			'id' => (int)$c->getId(),
			'uid' => $c->getUid(),
			'name' => $this->access->displayName($c->getUid()),
			'entryId' => (int)$c->getEntryId(),
			'day' => $e?->getDay(),
			'current' => $e === null ? null : $this->block((int)$e->getStartTs(), $e->getEndTs(), $e->getPauseMin(), $tz),
			'proposed' => $this->block($c->getProposedStart(), $c->getProposedEnd(), $c->getProposedPause(), $tz),
			'reason' => $c->getReason(),
			'status' => $c->getStatus(),
			'decidedBy' => $c->getDecidedBy(),
			'decidedAt' => Dates::isoOrNull($c->getDecidedAt()),
			'decisionNote' => $c->getDecisionNote(),
			'createdAt' => Dates::iso((int)$c->getCreatedAt()),
		];
	}

	/** @return list<array> */
	public function list(string $actor, string $scope, ?string $status): array {
		if ($status !== null && !in_array($status, ['pending', 'approved', 'rejected'], true)) {
			$status = null;
		}
		if ($scope === 'pending') {
			if (!$this->access->isChef($actor)) {
				throw new TgException('forbidden');
			}
			$uids = array_values(array_filter($this->access->memberUids(), fn ($u) => $this->access->policy()->canDecide($actor, $u)));
			$rows = $this->corrections->findPending($uids);
		} else {
			$rows = $this->corrections->findOfUser($actor, $status);
		}
		return array_map([$this, 'view'], $rows);
	}

	private function instant(mixed $v, string $day, \DateTimeZone $tz): int|null|false {
		if ($v === null || $v === '') {
			return null;
		}
		$iso = Dates::parseIso($v);
		if ($iso !== null) {
			return intdiv($iso, 60) * 60;
		}
		$m = Dates::parseHM($v);
		return $m === null ? false : Tz::wall($day, $m, $tz);
	}

	public function create(string $uid, array $in): array {
		$this->access->assertMember($uid);
		$eid = $in['entryId'] ?? null;
		$e = is_int($eid) || (is_string($eid) && ctype_digit($eid)) ? $this->entryOrNull((int)$eid) : null;
		if ($e === null || $e->getUid() !== $uid) {
			throw new TgException('validation', ['entryId']);
		}
		$now = $this->time->getTime();
		$tz = $this->config->tz();
		$err = [];
		$s = $this->instant($in['start'] ?? null, $e->getDay(), $tz);
		$t = $this->instant($in['end'] ?? null, $e->getDay(), $tz);
		if ($s === false) {
			$err[] = 'start';
		}
		if ($t === false) {
			$err[] = 'end';
		}
		$pause = null;
		if (array_key_exists('pauseMin', $in) && $in['pauseMin'] !== null && $in['pauseMin'] !== '') {
			$pause = Validator::pause($in['pauseMin']);
			if ($pause === null) {
				$err[] = 'pause';
			}
		}
		$r = Validator::reason($in['reason'] ?? '');
		if (!$r['ok']) {
			$err[] = 'reason';
		}
		if ($err === [] && $s === null && $t === null && $pause === null) {
			$err[] = 'start';
		}
		if ($err === []) {
			$fs = $s ?? (int)$e->getStartTs();
			$ft = $t ?? $e->getEndTs();
			if ($ft === null || $ft <= $fs || $ft - $fs > 86400) {
				$err[] = 'order';
			} elseif ($ft > $now) {
				$err[] = 'future';
			} elseif (($pause ?? $e->getPauseMin()) >= intdiv($ft - $fs, 60)) {
				$err[] = 'pause_long';
			}
		}
		if ($err !== []) {
			throw new TgException('validation', array_values(array_unique($err)));
		}
		$c = new Correction();
		$c->setUid($uid);
		$c->setEntryId((int)$e->getId());
		$c->setProposedStart($s === false ? null : $s);
		$c->setProposedEnd($t === false ? null : $t);
		$c->setProposedPause($pause);
		$c->setReason($r['reason']);
		$c->setStatus('pending');
		$c->setCreatedAt($now);
		/** @var Correction $c */
		$c = $this->corrections->insert($c);
		$this->audit->log($uid, $uid, 'correction.create', 'correction', (int)$c->getId(), null, $this->view($c));
		$this->effects->correctionRequested($c, $e->getDay());
		return $this->view($c);
	}

	/** @return array{correction:array,entry:?Entry} */
	public function decide(string $actor, int $id, mixed $decision, mixed $note): array {
		if (!in_array($decision, ['approve', 'reject'], true)) {
			throw new TgException('validation', ['decision']);
		}
		try {
			$c = $this->corrections->find($id);
		} catch (DoesNotExistException) {
			throw new TgException('not_found');
		}
		if (!$this->access->policy()->canView($actor, $c->getUid())) {
			throw new TgException('not_found');
		}
		$this->access->assertCanDecide($actor, $c->getUid());
		$status = $decision === 'approve' ? 'approved' : 'rejected';
		if (!$this->corrections->decide($id, $status, $actor, $this->time->getTime(), Sanitize::textOrNull($note, 255))) {
			throw new TgException('already_decided');
		}
		$c = $this->corrections->find($id);
		$entry = null;
		if ($status === 'approved') {
			$entry = $this->entryService->applyCorrection($c, $actor);
		}
		$this->audit->log($actor, $c->getUid(), 'correction.' . $status, 'correction', $id, ['status' => 'pending'], ['status' => $status]);
		$e = $this->entryOrNull((int)$c->getEntryId());
		$this->effects->correctionDecided($c, $e?->getDay() ?? '');
		return ['correction' => $this->view($c), 'entry' => $entry];
	}
}
