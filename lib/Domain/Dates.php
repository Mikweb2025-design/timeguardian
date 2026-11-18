<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Domain;

/**
 * Calendar helpers on plain 'YYYY-MM-DD' strings (no time zone involved) plus minute formatting.
 * Monday = 0 everywhere.
 */
final class Dates {
	/** UTC timestamp of the midnight of a valid day string, else null */
	public static function parseDay(mixed $s): ?int {
		if (!is_string($s) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $s, $m)) {
			return null;
		}
		if (!checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
			return null;
		}
		return gmmktime(0, 0, 0, (int)$m[2], (int)$m[3], (int)$m[1]);
	}

	public static function validDay(mixed $s): bool {
		return self::parseDay($s) !== null;
	}

	public static function day(int $utcTs): string {
		return gmdate('Y-m-d', $utcTs);
	}

	public static function add(string $day, int $n): string {
		$t = self::parseDay($day);
		if ($t === null) {
			throw new \InvalidArgumentException('bad day');
		}
		return gmdate('Y-m-d', $t + $n * 86400);
	}

	/** number of days from $a to $b (b - a) */
	public static function diff(string $a, string $b): int {
		return intdiv((int)self::parseDay($b) - (int)self::parseDay($a), 86400);
	}

	/** Monday = 0 */
	public static function dow(string $day): int {
		return (int)gmdate('N', (int)self::parseDay($day)) - 1;
	}

	public static function monday(string $day): string {
		return self::add($day, -self::dow($day));
	}

	/** @return array{year:int,week:int} ISO 8601 week */
	public static function isoWeek(string $day): array {
		$t = (int)self::parseDay($day);
		return ['year' => (int)gmdate('o', $t), 'week' => (int)gmdate('W', $t)];
	}

	/** 'HH:MM' (also 'H:MM') -> minutes since midnight, else null. 24:00 is invalid. */
	public static function parseHM(mixed $s): ?int {
		if ($s === null) {
			return null;
		}
		if (!is_string($s) && !is_int($s)) {
			return null;
		}
		if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/D', trim((string)$s), $m)) {
			return null;
		}
		return (int)$m[1] * 60 + (int)$m[2];
	}

	public static function hm(int $min): string {
		$min = (int)round($min);
		return str_pad((string)intdiv($min, 60), 2, '0', STR_PAD_LEFT) . ':' . str_pad((string)($min % 60), 2, '0', STR_PAD_LEFT);
	}

	/** "10,7 h" (en: "10.7 h"), rounded to 0.1 h like the original rule texts */
	public static function fmtH(int|float $min, string $lang = 'de'): string {
		$h = floor($min / 6 + 0.5) / 10;
		$t = number_format($h, 1, '.', '');
		return ($lang === 'en' ? $t : str_replace('.', ',', $t)) . ' h';
	}

	/** "9 h 05 min" */
	public static function fmtHM(int|float $min): string {
		$min = (int)round($min);
		return intdiv($min, 60) . ' h ' . str_pad((string)($min % 60), 2, '0', STR_PAD_LEFT) . ' min';
	}

	/** @return array{0:string,1:string}|null first and last day of 'YYYY-MM' */
	public static function monthRange(mixed $ym): ?array {
		if (!is_string($ym) || !preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/D', $ym, $m)) {
			return null;
		}
		$first = $ym . '-01';
		$last = gmdate('Y-m-d', gmmktime(0, 0, 0, (int)$m[2] + 1, 0, (int)$m[1]));
		return [$first, $last];
	}

	public static function iso(int $ts): string {
		return gmdate('Y-m-d\TH:i:s\Z', $ts);
	}

	public static function isoOrNull(?int $ts): ?string {
		return $ts === null ? null : self::iso($ts);
	}

	/** parses '2026-11-17T07:02:00Z' (also with +00:00 / fractions), else null */
	public static function parseIso(mixed $s): ?int {
		if (!is_string($s) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?(\.\d+)?(Z|[+-]\d{2}:\d{2})$/D', $s)) {
			return null;
		}
		try {
			return (new \DateTimeImmutable($s))->getTimestamp();
		} catch (\Throwable) {
			return null;
		}
	}

	/** @return list<string> every day from..to inclusive */
	public static function range(string $from, string $to): array {
		$out = [];
		for ($d = $from; $d <= $to; $d = self::add($d, 1)) {
			$out[] = $d;
		}
		return $out;
	}
}
