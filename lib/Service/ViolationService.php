<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Service;

use OCA\TimeGuardian\Db\AbsenceMapper;
use OCA\TimeGuardian\Db\EntryMapper;
use OCA\TimeGuardian\Db\Violation;
use OCA\TimeGuardian\Db\ViolationMapper;
use OCA\TimeGuardian\Domain\Dates;
use OCA\TimeGuardian\Domain\Report;
use OCA\TimeGuardian\Domain\Rules;
use OCP\AppFramework\Utility\ITimeFactory;

/**
 * Keeps tg_violation (the materialized rule hits) in sync with the entries. The ISO weeks touched by a change are
 * re-evaluated completely, so WEEK48 and REST11 stay correct.
 */
class ViolationService {
	public function __construct(
		private EntryMapper $entries,
		private AbsenceMapper $absences,
		private ViolationMapper $violations,
		private ConfigService $config,
		private ITimeFactory $time,
	) {
	}

	/**
	 * Re-evaluates the weeks around $fromDay..$toDay of one user.
	 * @return array{all:list<array>,new:list<array>} rule hits of the touched weeks, and those that did not exist before
	 */
	public function refresh(string $uid, string $fromDay, string $toDay): array {
		$wFrom = Dates::monday($fromDay);
		$wTo = Dates::add(Dates::monday($toDay), 6);
		$tz = $this->config->tz();
		$now = $this->time->getTime();
		$rows = Report::rows(array_map(fn ($e) => $e->toRow(), $this->entries->findRange($uid, Dates::add($wFrom, -1), $wTo)), $tz, $now, false);
		$absent = [];
		foreach ($this->absences->findOverlapping([$uid], $wFrom, $wTo, 'approved') as $a) {
			for ($d = max($wFrom, $a->getFromDay()); $d <= min($wTo, $a->getToDay()); $d = Dates::add($d, 1)) {
				$absent[$uid . '|' . $d] = true;
			}
		}
		$fresh = array_values(array_filter(Rules::evaluate($rows, $absent, true), fn ($v) => $v['day'] >= $wFrom && $v['day'] <= $wTo));
		$existing = [];
		foreach ($this->violations->findRange($uid, $wFrom, $wTo) as $v) {
			$existing[$v->getRule() . '|' . $v->getDay()] = $v;
		}
		$new = [];
		$keep = [];
		foreach ($fresh as $v) {
			$k = $v['rule'] . '|' . $v['day'];
			$keep[$k] = true;
			if (isset($existing[$k])) {
				continue;
			}
			$row = new Violation();
			$row->setUid($uid);
			$row->setDay($v['day']);
			$row->setRule($v['rule']);
			$row->setLevel($v['level']);
			$row->setTextKey($v['rule']);
			$row->setParams(json_encode(['value' => $v['value']]));
			$row->setCreatedAt($now);
			$this->violations->insert($row);
			$new[] = $v;
		}
		foreach ($existing as $k => $row) {
			if (!isset($keep[$k])) {
				$this->violations->deleteById((int)$row->getId());
			}
		}
		return ['all' => $fresh, 'new' => $new];
	}

	/** @return list<array> stored rule hits in a range (domain rows) */
	public function range(string $uid, string $from, string $to): array {
		return array_map(fn (Violation $v) => $v->toRow(), $this->violations->findRange($uid, $from, $to));
	}

	/**
	 * @param list<string> $uids
	 * @return list<array>
	 */
	public function rangeForUsers(array $uids, string $from, string $to): array {
		return array_map(fn (Violation $v) => $v->toRow(), $this->violations->findRangeForUsers($uids, $from, $to));
	}
}
