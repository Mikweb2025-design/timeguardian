<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Domain;

/** German public holidays per federal state (Bundesland), Easter based days computed with the Gregorian algorithm. */
final class Holidays {
	public const STATES = ['BW', 'BY', 'BE', 'BB', 'HB', 'HH', 'HE', 'MV', 'NI', 'NW', 'RP', 'SL', 'SN', 'ST', 'SH', 'TH'];

	/** Easter Sunday as 'Y-m-d' (Meeus/Jones/Butcher) */
	public static function easter(int $year): string {
		$a = $year % 19;
		$b = intdiv($year, 100);
		$c = $year % 100;
		$d = intdiv($b, 4);
		$e = $b % 4;
		$f = intdiv($b + 8, 25);
		$g = intdiv($b - $f + 1, 3);
		$h = (19 * $a + $b - $d - $g + 15) % 30;
		$i = intdiv($c, 4);
		$k = $c % 4;
		$l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
		$m = intdiv($a + 11 * $h + 22 * $l, 451);
		$n = $h + $l - 7 * $m + 114;
		$month = intdiv($n, 31);
		$day = ($n % 31) + 1;
		return sprintf('%04d-%02d-%02d', $year, $month, $day);
	}

	/**
	 * @return array<string,string> day => holiday key (see Messages::holiday)
	 */
	public static function forYear(int $year, string $state): array {
		$state = strtoupper($state);
		if (!in_array($state, self::STATES, true)) {
			$state = 'BE';
		}
		$y = sprintf('%04d', $year);
		$easter = self::easter($year);
		$e = static fn (int $n): string => Dates::add($easter, $n);
		$h = [
			$y . '-01-01' => 'newyear',
			$e(-2) => 'goodfriday',
			$e(1) => 'eastermonday',
			$y . '-05-01' => 'labourday',
			$e(39) => 'ascension',
			$e(50) => 'whitmonday',
			$y . '-10-03' => 'unityday',
			$y . '-12-25' => 'christmas1',
			$y . '-12-26' => 'christmas2',
		];
		if (in_array($state, ['BW', 'BY', 'ST'], true)) {
			$h[$y . '-01-06'] = 'epiphany';
		}
		if ($state === 'BE' || ($state === 'MV' && $year >= 2023)) {
			$h[$y . '-03-08'] = 'womensday';
		}
		if ($state === 'BB') {
			$h[$easter] = 'eastersunday';
			$h[$e(49)] = 'whitsunday';
		}
		if (in_array($state, ['BW', 'BY', 'HE', 'NW', 'RP', 'SL'], true)) {
			$h[$e(60)] = 'corpuschristi';
		}
		if ($state === 'SL') {
			$h[$y . '-08-15'] = 'assumption';
		}
		if ($state === 'TH') {
			$h[$y . '-09-20'] = 'childrensday';
		}
		if (in_array($state, ['BB', 'HB', 'HH', 'MV', 'NI', 'SN', 'ST', 'SH', 'TH'], true)) {
			$h[$y . '-10-31'] = 'reformation';
		}
		if (in_array($state, ['BW', 'BY', 'NW', 'RP', 'SL'], true)) {
			$h[$y . '-11-01'] = 'allsaints';
		}
		if ($state === 'SN') {
			$d = $y . '-11-22';
			while (Dates::dow($d) !== 2) {
				$d = Dates::add($d, -1);
			}
			$h[$d] = 'repentance';
		}
		ksort($h);
		return $h;
	}

	/**
	 * Holidays for every day of a range.
	 * @return array<string,string> day => key
	 */
	public static function inRange(string $from, string $to, string $state): array {
		$out = [];
		$y1 = (int)substr($from, 0, 4);
		$y2 = (int)substr($to, 0, 4);
		for ($y = $y1; $y <= $y2; $y++) {
			foreach (self::forYear($y, $state) as $d => $k) {
				if ($d >= $from && $d <= $to) {
					$out[$d] = $k;
				}
			}
		}
		return $out;
	}
}
