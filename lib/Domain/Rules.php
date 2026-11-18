<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Domain;

/**
 * Working time rules of the German Arbeitszeitgesetz (ArbZG). Deterministic, no I/O.
 * Semantics ported 1:1 from the reference implementation: DAY10 §3, DAY8 hint, BREAK30 / BREAK45 §4, REST11 §5,
 * SUN §9, WEEK48 hint, ABSENT.
 *
 * Input rows: ['uid'=>string,'day'=>'YYYY-MM-DD','startMin'=>int,'endMin'=>int,'pauseMin'=>int]
 * startMin/endMin are minutes since the local midnight of 'day' (endMin may exceed 1440 for night shifts).
 * Not legal advice.
 */
final class Rules {
	public const DAY10 = 'DAY10';
	public const DAY8 = 'DAY8';
	public const BREAK30 = 'BREAK30';
	public const BREAK45 = 'BREAK45';
	public const REST11 = 'REST11';
	public const SUN = 'SUN';
	public const WEEK48 = 'WEEK48';
	public const ABSENT = 'ABSENT';

	public const ALL = ['DAY10', 'DAY8', 'BREAK30', 'BREAK45', 'REST11', 'SUN', 'WEEK48', 'ABSENT'];
	public const LEVEL_VIOLATION = 'verstoss';
	public const LEVEL_HINT = 'hinweis';

	/** a gap between two blocks of one day counts as a break from this length on (ArbZG § 4: at least 15 minutes) */
	public const MIN_GAP_BREAK = 15;

	public const DAY_LIMIT = 600;
	public const DAY_HINT = 480;
	public const WEEK_LIMIT = 2880;
	public const REST_MIN = 660;

	/**
	 * Aggregates rows to days.
	 * @param list<array> $rows
	 * @return array<string,array<string,array{uid:string,day:string,first:int,last:int,net:int,pause:int,n:int}>> uid => day => aggregate
	 */
	public static function dayMap(array $rows, bool $deriveGaps = true): array {
		$blocks = [];
		$m = [];
		foreach ($rows as $r) {
			$s = (int)($r['startMin'] ?? -1);
			$e = (int)($r['endMin'] ?? -1);
			$p = (int)($r['pauseMin'] ?? 0);
			$day = $r['day'] ?? '';
			$uid = (string)($r['uid'] ?? '');
			if ($s < 0 || $e <= $s || !Dates::validDay($day)) {
				continue;
			}
			if (!isset($m[$uid][$day])) {
				$m[$uid][$day] = ['uid' => $uid, 'day' => $day, 'first' => $s, 'last' => $e, 'net' => 0, 'pause' => 0, 'n' => 0];
			}
			$d = &$m[$uid][$day];
			$d['first'] = min($d['first'], $s);
			$d['last'] = max($d['last'], $e);
			$d['net'] += max(0, $e - $s - $p);
			$d['pause'] += $p;
			$d['n']++;
			unset($d);
			$blocks[$uid][$day][] = [$s, $e];
		}
		if ($deriveGaps) {
			foreach ($blocks as $uid => $days) {
				foreach ($days as $day => $list) {
					usort($list, static fn ($a, $b) => $a[0] <=> $b[0]);
					$cursor = $list[0][1];
					$gaps = 0;
					for ($i = 1, $n = count($list); $i < $n; $i++) {
						if ($list[$i][0] - $cursor >= self::MIN_GAP_BREAK) {
							$gaps += $list[$i][0] - $cursor;
						}
						$cursor = max($cursor, $list[$i][1]);
					}
					$m[$uid][$day]['pause'] += $gaps;
				}
			}
		}
		return $m;
	}

	/**
	 * @param list<array> $rows
	 * @param array<string,bool> $absent set of "uid|day" for approved absences
	 * @return list<array{uid:string,day:string,rule:string,level:string,value:int,key:string}> newest day first
	 */
	public static function evaluate(array $rows, array $absent = [], bool $deriveGaps = true): array {
		$dm = self::dayMap($rows, $deriveGaps);
		$out = [];
		$wk = [];
		foreach ($dm as $uid => $days) {
			ksort($days);
			foreach ($days as $day => $d) {
				$add = static function (string $rule, string $level, int $v) use (&$out, $uid, $day): void {
					$out[] = ['uid' => (string)$uid, 'day' => $day, 'rule' => $rule, 'level' => $level, 'value' => $v, 'key' => $uid . '|' . $day . '|' . $rule];
				};
				if ($d['net'] > self::DAY_LIMIT) {
					$add(self::DAY10, self::LEVEL_VIOLATION, $d['net']);
				} elseif ($d['net'] > self::DAY_HINT) {
					$add(self::DAY8, self::LEVEL_HINT, $d['net']);
				}
				if ($d['net'] > 540 && $d['pause'] < 45) {
					$add(self::BREAK45, self::LEVEL_VIOLATION, $d['pause']);
				} elseif ($d['net'] > 360 && $d['pause'] < 30) {
					$add(self::BREAK30, self::LEVEL_VIOLATION, $d['pause']);
				}
				$prev = $dm[$uid][Dates::add($day, -1)] ?? null;
				if ($prev !== null) {
					$rest = 1440 - $prev['last'] + $d['first'];
					if ($rest < self::REST_MIN) {
						$add(self::REST11, self::LEVEL_VIOLATION, $rest);
					}
				}
				if (Dates::dow($day) === 6) {
					$add(self::SUN, self::LEVEL_HINT, $d['net']);
				}
				if (isset($absent[$uid . '|' . $day])) {
					$add(self::ABSENT, self::LEVEL_HINT, 0);
				}
				$w = Dates::monday($day);
				if (!isset($wk[$uid][$w])) {
					$wk[$uid][$w] = ['net' => 0, 'last' => $day];
				}
				$wk[$uid][$w]['net'] += $d['net'];
				if ($day > $wk[$uid][$w]['last']) {
					$wk[$uid][$w]['last'] = $day;
				}
			}
		}
		foreach ($wk as $uid => $weeks) {
			foreach ($weeks as $x) {
				if ($x['net'] > self::WEEK_LIMIT) {
					$out[] = ['uid' => (string)$uid, 'day' => $x['last'], 'rule' => self::WEEK48, 'level' => self::LEVEL_HINT, 'value' => $x['net'], 'key' => $uid . '|' . $x['last'] . '|WEEK48'];
				}
			}
		}
		usort($out, static function (array $a, array $b): int {
			if ($a['day'] !== $b['day']) {
				return $a['day'] < $b['day'] ? 1 : -1;
			}
			return strcmp($a['key'], $b['key']);
		});
		return $out;
	}

	/**
	 * Violations that exist in $after but not in $before (by key).
	 * @param list<array> $before @param list<array> $after
	 * @return list<array>
	 */
	public static function newViolations(array $before, array $after): array {
		$seen = [];
		foreach ($before as $v) {
			$seen[$v['key']] = true;
		}
		return array_values(array_filter($after, static fn (array $v) => !isset($seen[$v['key']])));
	}

	/** localized text of a violation */
	public static function text(array $v, string $lang): string {
		return Messages::rule((string)$v['rule'], (int)($v['value'] ?? 0), $lang);
	}
}
