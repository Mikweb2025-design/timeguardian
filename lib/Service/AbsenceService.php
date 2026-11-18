<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Service;

use OCA\TimeGuardian\Db\Absence;
use OCA\TimeGuardian\Db\AbsenceMapper;
use OCA\TimeGuardian\Domain\Dates;
use OCA\TimeGuardian\Domain\Holidays;
use OCA\TimeGuardian\Domain\Sanitize;
use OCA\TimeGuardian\Domain\TgException;
use OCA\TimeGuardian\Domain\Tz;
use OCA\TimeGuardian\Domain\Validator;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;

/** Absence requests: vacation, sick, flex time, business trip. Managers decide. */
class AbsenceService {
	public function __construct(
		private AbsenceMapper $absences,
		private ConfigService $config,
		private UserConfService $userConf,
		private AccessService $access,
		private AuditService $audit,
		private EffectsService $effects,
		private ViolationService $violations,
		private ITimeFactory $time,
	) {
	}

	private function workingDays(Absence $a): int {
		$mask = $this->userConf->get($a->getUid())['daysMask'];
		$hol = Holidays::inRange($a->getFromDay(), $a->getToDay(), $this->config->state());
		$n = 0;
		for ($d = $a->getFromDay(); $d <= $a->getToDay(); $d = Dates::add($d, 1)) {
			if ((($mask >> Dates::dow($d)) & 1) && !isset($hol[$d])) {
				$n++;
			}
		}
		return $n;
	}

	public function view(Absence $a): array {
		return [
			'id' => (int)$a->getId(),
			'uid' => $a->getUid(),
			'name' => $this->access->displayName($a->getUid()),
			'kind' => $a->getKind(),
			'fromDay' => $a->getFromDay(),
			'toDay' => $a->getToDay(),
			'days' => $this->workingDays($a),
			'status' => $a->getStatus(),
			'decidedBy' => $a->getDecidedBy(),
			'decidedAt' => Dates::isoOrNull($a->getDecidedAt()),
			'note' => $a->getNote(),
			'createdAt' => Dates::iso((int)$a->getCreatedAt()),
		];
	}

	/** @return list<string> users whose requests the actor may see */
	private function visible(string $actor): array {
		return array_values(array_filter($this->access->memberUids(), fn ($u) => $this->access->policy()->canView($actor, $u)));
	}

	/** @return list<array> */
	public function list(string $actor, string $scope, ?string $from, ?string $to): array {
		$from = ($from !== null && Dates::validDay($from)) ? $from : Dates::add(Tz::day($this->time->getTime(), $this->config->tz()), -400);
		$to = ($to !== null && Dates::validDay($to)) ? $to : Dates::add($from, 800);
		if ($scope === 'pending' || $scope === 'team') {
			if (!$this->access->isChef($actor)) {
				throw new TgException('forbidden');
			}
			$uids = $this->visible($actor);
			$rows = $scope === 'pending'
				? array_values(array_filter($this->absences->findPending($uids), fn (Absence $a) => $this->access->policy()->canDecide($actor, $a->getUid())))
				: $this->absences->findOverlapping($uids, $from, $to);
		} else {
			$rows = array_values(array_filter($this->absences->findAllOfUser($actor), fn (Absence $a) => $a->getToDay() >= $from && $a->getFromDay() <= $to));
		}
		return array_map([$this, 'view'], $rows);
	}

	public function create(string $uid, array $in): array {
		$this->access->assertMember($uid);
		$now = $this->time->getTime();
		$v = Validator::absence($in, Tz::day($now, $this->config->tz()));
		if (!$v['ok']) {
			throw new TgException('validation', $v['errors']);
		}
		$x = $v['absence'];
		foreach ($this->absences->findOverlapping([$uid], $x['fromDay'], $x['toDay']) as $o) {
			if ($o->getStatus() !== 'rejected') {
				throw new TgException('overlap');
			}
		}
		$a = new Absence();
		$a->setUid($uid);
		$a->setKind($x['kind']);
		$a->setFromDay($x['fromDay']);
		$a->setToDay($x['toDay']);
		$a->setStatus('pending');
		$a->setNote($x['note']);
		$a->setCreatedAt($now);
		/** @var Absence $a */
		$a = $this->absences->insert($a);
		$this->audit->log($uid, $uid, 'absence.create', 'absence', (int)$a->getId(), null, $this->view($a));
		$this->effects->absenceRequested($a);
		return $this->view($a);
	}

	private function load(int $id): Absence {
		try {
			return $this->absences->find($id);
		} catch (DoesNotExistException) {
			throw new TgException('not_found');
		}
	}

	public function delete(string $uid, int $id): void {
		$a = $this->load($id);
		if ($a->getUid() !== $uid) {
			throw new TgException('not_found');
		}
		$before = $this->view($a);
		$wasApproved = $a->getStatus() === 'approved';
		$this->absences->delete($a);
		$this->effects->absenceRemoved($a);
		$this->audit->log($uid, $uid, 'absence.delete', 'absence', $id, $before, null);
		if ($wasApproved) {
			$this->violations->refresh($uid, $a->getFromDay(), $a->getToDay());
		}
	}

	public function decide(string $actor, int $id, mixed $decision, mixed $note): array {
		if (!in_array($decision, ['approve', 'reject'], true)) {
			throw new TgException('validation', ['decision']);
		}
		$a = $this->load($id);
		if (!$this->access->policy()->canView($actor, $a->getUid())) {
			throw new TgException('not_found');
		}
		$this->access->assertCanDecide($actor, $a->getUid());
		$status = $decision === 'approve' ? 'approved' : 'rejected';
		if (!$this->absences->decide($id, $status, $actor, $this->time->getTime())) {
			throw new TgException('already_decided');
		}
		$a = $this->load($id);
		$n = Sanitize::textOrNull($note, 255);
		if ($n !== null) {
			$a->setNote(trim(($a->getNote() !== null ? $a->getNote() . ' | ' : '') . $n));
		}
		$eventUid = $this->effects->absenceDecided($a, $actor);
		if ($eventUid !== null) {
			$a->setCalendarUid($eventUid);
		}
		$this->absences->update($a);
		$this->audit->log($actor, $a->getUid(), 'absence.' . $status, 'absence', $id, ['status' => 'pending'], ['status' => $status]);
		if ($status === 'approved') {
			$this->violations->refresh($a->getUid(), $a->getFromDay(), $a->getToDay());
		}
		return $this->view($a);
	}
}
