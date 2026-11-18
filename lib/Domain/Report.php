<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Domain;

/**
 * Builds the JSON shapes of the API (docs/API.md) from plain arrays. No I/O.
 *
 * Entry input: ['id','uid','day','startTs','endTs'(null=open),'pauseMin','project','note','source','createdAt','updatedAt']
 * conf: ['weeklyMin'=>int,'daysMask'=>int]
 */
final class Report {
	public static function entryView(array $e, int $now, \DateTimeZone $tz, string $today, bool $own): array {
		$end = $e['endTs'] ?? null;
		$net = max(0, intdiv(($end ?? $now) - (int)$e['startTs'], 60) - (int)$e['pauseMin']);
		return [
			'id' => (int)$e['id'],
			'uid' => (string)$e['uid'],
			'day' => (string)$e['day'],
			'start' => Dates::iso((int)$e['startTs']),
			'end' => Dates::isoOrNull($end === null ? null : (int)$end),
			'startHM' => Tz::hm((int)$e['startTs'], $tz),
			'endHM' => $end === null ? null : Tz::hm((int)$end, $tz),
			'pauseMin' => (int)$e['pauseMin'],
			'netMin' => $net,
			'running' => $end === null,
			'project' => $e['project'] ?? null,
			'note' => $e['note'] ?? null,
			'source' => (string)($e['source'] ?? 'scan'),
			'createdAt' => Dates::iso((int)($e['createdAt'] ?? $e['startTs'])),
			'updatedAt' => Dates::iso((int)($e['updatedAt'] ?? $e['startTs'])),
			'editable' => $own && (string)$e['day'] >= Dates::add($today, -Validator::EDIT_DAYS),
		];
	}

	public static function violationView(array $v, string $lang): array {
		return [
			'uid' => (string)$v['uid'],
			'day' => (string)$v['day'],
			'rule' => (string)$v['rule'],
			'level' => (string)$v['level'],
			'value' => (int)($v['value'] ?? 0),
			'text' => Rules::text($v, $lang),
		];
	}

	/**
	 * Rows for the rules. Open entries are included (clipped to $now) only when $includeOpen.
	 * @param list<array> $entries
	 * @return list<array>
	 */
	public static function rows(array $entries, \DateTimeZone $tz, int $now, bool $includeOpen): array {
		$out = [];
		foreach ($entries as $e) {
			$end = $e['endTs'] ?? null;
			if ($end === null) {
				if (!$includeOpen) {
					continue;
				}
				$end = $now;
			}
			$day = (string)$e['day'];
			$out[] = [
				'uid' => (string)$e['uid'],
				'day' => $day,
				'startMin' => Tz::offsetMin((int)$e['startTs'], $day, $tz),
				'endMin' => Tz::offsetMin((int)$end, $day, $tz),
				'pauseMin' => (int)$e['pauseMin'],
			];
		}
		return $out;
	}

	/** net minutes per day of closed entries (for the account) */
	public static function netByDay(array $entries): array {
		$o = [];
		foreach ($entries as $e) {
			if (($e['endTs'] ?? null) === null) {
				continue;
			}
			$o[$e['day']] = ($o[$e['day']] ?? 0) + max(0, intdiv((int)$e['endTs'] - (int)$e['startTs'], 60) - (int)$e['pauseMin']);
		}
		return $o;
	}

	/**
	 * @param list<array> $entries entries of ONE user in the range
	 * @param list<array> $violations rows of ['uid','day','rule','level','value']
	 * @param array<string,array{kind:string,id:int}> $absByDay approved absences per day
	 * @return list<array>
	 */
	public static function days(string $from, string $to, array $entries, array $violations, array $absByDay, array $conf, string $state, \DateTimeZone $tz, string $lang, string $today, int $now, bool $own): array {
		$hol = Holidays::inRange($from, $to, $state);
		$rows = self::rows($entries, $tz, $now, true);
		$dm = Rules::dayMap($rows, true);
		$byDay = [];
		foreach ($entries as $e) {
			$byDay[$e['day']][] = $e;
		}
		$vByDay = [];
		foreach ($violations as $v) {
			$vByDay[$v['day']][] = self::violationView($v, $lang);
		}
		$out = [];
		foreach (Dates::range($from, $to) as $d) {
			$agg = null;
			foreach ($dm as $perUid) {
				$agg = $perUid[$d] ?? $agg;
			}
			$net = $agg['net'] ?? 0;
			$abs = $absByDay[$d] ?? null;
			$x = Balance::day($d, (int)$conf['weeklyMin'], (int)$conf['daysMask'], $hol, $abs, $net);
			$list = $byDay[$d] ?? [];
			usort($list, static fn ($a, $b) => $a['startTs'] <=> $b['startTs']);
			$out[] = [
				'day' => $d,
				'dow' => Dates::dow($d),
				'isToday' => $d === $today,
				'holiday' => isset($hol[$d]) ? ['key' => $hol[$d], 'name' => Messages::holiday($hol[$d], $lang)] : null,
				'absence' => $abs,
				'sollMin' => $x['sollMin'],
				'netMin' => $net,
				'pauseMin' => $agg['pause'] ?? 0,
				'creditMin' => $x['creditMin'],
				'deltaMin' => $x['deltaMin'],
				'firstHM' => $agg ? Dates::hm($agg['first'] % 1440) : null,
				'lastHM' => $agg ? Dates::hm($agg['last'] % 1440) : null,
				'entries' => array_map(static fn ($e) => self::entryView($e, $now, $tz, $today, $own), $list),
				'violations' => $vByDay[$d] ?? [],
			];
		}
		return $out;
	}

	/** totals over day objects */
	public static function totals(array $days): array {
		$t = ['sollMin' => 0, 'netMin' => 0, 'creditMin' => 0, 'deltaMin' => 0];
		foreach ($days as $d) {
			foreach ($t as $k => $_) {
				$t[$k] += $d[$k];
			}
		}
		return $t;
	}

	/** net minutes per project over entries (open ones clipped to now) */
	public static function byProject(array $entries, int $now): array {
		$o = [];
		foreach ($entries as $e) {
			$p = $e['project'] ?? '';
			$p = $p === null ? '' : $p;
			$o[$p] = ($o[$p] ?? 0) + max(0, intdiv(($e['endTs'] ?? $now) - (int)$e['startTs'], 60) - (int)$e['pauseMin']);
		}
		arsort($o);
		$out = [];
		foreach ($o as $p => $n) {
			$out[] = ['project' => (string)$p, 'netMin' => $n];
		}
		return $out;
	}

	/** @return array week header for a day */
	public static function weekHeader(string $date): array {
		$from = Dates::monday($date);
		$w = Dates::isoWeek($from);
		return ['from' => $from, 'to' => Dates::add($from, 6), 'kw' => $w['week'], 'year' => $w['year']];
	}
}
