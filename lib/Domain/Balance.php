<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Domain;

/**
 * Time account: target per day from weekly minutes and working days, public holidays, absences, running balance.
 *
 * - target of a day = weekly minutes / number of working days, 0 on public holidays and on days outside the mask
 * - approved vacation, sick leave and business trips credit the target of the day (counts as fulfilled)
 * - approved flex time does NOT credit: the target stays open, so the balance drops (that is the point of flex time)
 * - balance = sum(net + credit - target) over the completed days
 */
final class Balance {
	/** absence kinds that count as target fulfilled */
	public const CREDIT_KINDS = ['vacation', 'sick', 'trip'];

	public static function workdays(int $mask): int {
		$n = 0;
		for ($i = 0; $i < 7; $i++) {
			if (($mask >> $i) & 1) {
				$n++;
			}
		}
		return $n;
	}

	/** @param array<string,string> $holidays day => key */
	public static function target(string $day, int $weeklyMin, int $mask, array $holidays): int {
		$n = self::workdays($mask);
		if ($n === 0 || !(($mask >> Dates::dow($day)) & 1) || isset($holidays[$day])) {
			return 0;
		}
		return (int)round($weeklyMin / $n);
	}

	/**
	 * Expands approved absences to days. Credit kinds win over flex when ranges overlap.
	 * @param list<array{id:int,kind:string,fromDay:string,toDay:string}> $absences approved ones
	 * @return array<string,array{kind:string,id:int}>
	 */
	public static function expandAbsences(array $absences, string $from, string $to): array {
		$out = [];
		foreach ($absences as $a) {
			$s = max($from, $a['fromDay']);
			$e = min($to, $a['toDay']);
			for ($d = $s; $d <= $e; $d = Dates::add($d, 1)) {
				$cur = $out[$d] ?? null;
				if ($cur === null || (!in_array($cur['kind'], self::CREDIT_KINDS, true) && in_array($a['kind'], self::CREDIT_KINDS, true))) {
					$out[$d] = ['kind' => $a['kind'], 'id' => (int)$a['id']];
				}
			}
		}
		return $out;
	}

	/**
	 * One day of the account.
	 * @return array{sollMin:int,netMin:int,creditMin:int,deltaMin:int}
	 */
	public static function day(string $day, int $weeklyMin, int $mask, array $holidays, ?array $absence, int $net): array {
		$soll = self::target($day, $weeklyMin, $mask, $holidays);
		$credit = ($absence !== null && in_array($absence['kind'], self::CREDIT_KINDS, true)) ? $soll : 0;
		return ['sollMin' => $soll, 'netMin' => $net, 'creditMin' => $credit, 'deltaMin' => $net + $credit - $soll];
	}

	/**
	 * Running balance over completed days since..through with a per-month history (last 12 months, oldest first).
	 * @param array<string,int> $netByDay
	 * @param list<array{id:int,kind:string,fromDay:string,toDay:string}> $absences approved
	 * @return array{balanceMin:int,asOf:string,since:string,history:list<array>}
	 */
	public static function account(string $since, string $through, int $weeklyMin, int $mask, string $state, array $netByDay, array $absences): array {
		if ($through < $since) {
			return ['balanceMin' => 0, 'asOf' => $through, 'since' => $since, 'history' => []];
		}
		$hol = Holidays::inRange($since, $through, $state);
		$abs = self::expandAbsences($absences, $since, $through);
		$months = [];
		$bal = 0;
		for ($d = $since; $d <= $through; $d = Dates::add($d, 1)) {
			$x = self::day($d, $weeklyMin, $mask, $hol, $abs[$d] ?? null, $netByDay[$d] ?? 0);
			$bal += $x['deltaMin'];
			$ym = substr($d, 0, 7);
			if (!isset($months[$ym])) {
				$months[$ym] = ['ym' => $ym, 'sollMin' => 0, 'istMin' => 0, 'creditMin' => 0, 'deltaMin' => 0, 'balanceMin' => 0];
			}
			$months[$ym]['sollMin'] += $x['sollMin'];
			$months[$ym]['istMin'] += $x['netMin'];
			$months[$ym]['creditMin'] += $x['creditMin'];
			$months[$ym]['deltaMin'] += $x['deltaMin'];
			$months[$ym]['balanceMin'] = $bal;
		}
		return ['balanceMin' => $bal, 'asOf' => $through, 'since' => $since, 'history' => array_slice(array_values($months), -12)];
	}
}
