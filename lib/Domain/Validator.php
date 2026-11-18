<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Domain;

/** Input validation: everything the browser sends is checked here. */
final class Validator {
	public const KINDS = ['vacation', 'sick', 'flex', 'trip'];
	public const MODES = ['simple', 'pause'];
	public const SOURCES = ['scan', 'manual', 'correction', 'seed'];
	/** manual bookings may go this many days back */
	public const MANUAL_BACK_DAYS = 31;
	/** own entries may be edited/deleted this many days back, older ones need a correction */
	public const EDIT_DAYS = 7;

	/**
	 * Resolves a start/end value: local 'HH:MM' on $day, or an ISO instant.
	 * @return int|null|false int = instant, null = missing, false = invalid
	 */
	private static function instant(mixed $v, string $day, \DateTimeZone $tz): int|null|false {
		if ($v === null || $v === '') {
			return null;
		}
		$iso = Dates::parseIso($v);
		if ($iso !== null) {
			return $iso;
		}
		$m = Dates::parseHM($v);
		if ($m === null) {
			return false;
		}
		return Tz::wall($day, $m, $tz);
	}

	/**
	 * Manual entry (create). All of day, start, end required (day may be derived from an ISO start).
	 * @param list<string> $projects allowed projects, empty = free text
	 * @return array{ok:bool,errors:list<string>,entry:array{day:string,startTs:int,endTs:int,pauseMin:int,project:?string,note:?string}}
	 */
	public static function entry(array $in, int $now, \DateTimeZone $tz, array $projects = []): array {
		$err = [];
		$e = ['day' => '', 'startTs' => 0, 'endTs' => 0, 'pauseMin' => 0, 'project' => null, 'note' => null];
		$today = Tz::day($now, $tz);
		$day = isset($in['day']) ? trim((string)$in['day']) : '';
		if ($day === '' && isset($in['start']) && ($iso = Dates::parseIso($in['start'])) !== null) {
			$day = Tz::day($iso, $tz);
		}
		if (!Dates::validDay($day)) {
			$err[] = 'day';
			$day = $today;
		} elseif ($day > $today) {
			$err[] = 'day_future';
		} elseif ($day < Dates::add($today, -self::MANUAL_BACK_DAYS)) {
			$err[] = 'day_old';
		}
		$s = self::instant($in['start'] ?? null, $day, $tz);
		$t = self::instant($in['end'] ?? null, $day, $tz);
		if ($s === null || $s === false) {
			$err[] = 'start';
		}
		if ($t === null || $t === false) {
			$err[] = 'end';
		}
		if (is_int($s) && is_int($t)) {
			$s = intdiv($s, 60) * 60;
			$t = intdiv($t, 60) * 60;
			if ($t <= $s || $t - $s > 86400) {
				$err[] = 'order';
			} elseif ($t > $now) {
				$err[] = 'future';
			}
			$e['startTs'] = $s;
			$e['endTs'] = $t;
		}
		$p = self::pause($in['pauseMin'] ?? $in['pause'] ?? 0);
		if ($p === null) {
			$err[] = 'pause';
		} else {
			$e['pauseMin'] = $p;
			if (is_int($s) && is_int($t) && $t > $s && $p >= intdiv($t - $s, 60)) {
				$err[] = 'pause_long';
			}
		}
		$proj = self::project($in['project'] ?? null, $projects);
		if ($proj === false) {
			$err[] = 'project';
		} else {
			$e['project'] = $proj;
		}
		$e['note'] = Sanitize::textOrNull($in['note'] ?? null, 255);
		$e['day'] = $day;
		return ['ok' => $err === [], 'errors' => array_values(array_unique($err)), 'entry' => $e];
	}

	/** whole minutes 0..240 or null */
	public static function pause(mixed $v): ?int {
		if ($v === null || $v === '') {
			return 0;
		}
		if (is_string($v)) {
			$v = str_replace(',', '.', trim($v));
			if (!is_numeric($v)) {
				return null;
			}
		}
		if (!is_int($v) && !is_float($v) && !is_string($v)) {
			return null;
		}
		$f = (float)$v;
		if (!is_finite($f) || $f < 0 || $f > 240 || floor($f) !== $f) {
			return null;
		}
		return (int)$f;
	}

	/** @return string|null|false project name, null (none) or false (not allowed) */
	public static function project(mixed $v, array $allowed): string|null|false {
		$p = Sanitize::text($v ?? '', 64);
		if ($p === '') {
			return null;
		}
		if ($allowed !== [] && !in_array($p, $allowed, true)) {
			return false;
		}
		return $p;
	}

	/**
	 * Partial update of an existing entry. Missing keys keep the old value.
	 * @param array{day:string,startTs:int,endTs:?int,pauseMin:int,project:?string,note:?string} $old
	 * @return array{ok:bool,errors:list<string>,entry:array}
	 */
	public static function entryUpdate(array $in, array $old, int $now, \DateTimeZone $tz, array $projects = []): array {
		$merged = [
			'day' => $in['day'] ?? $old['day'],
			'start' => $in['start'] ?? Dates::iso($old['startTs']),
			'end' => $in['end'] ?? ($old['endTs'] === null ? null : Dates::iso($old['endTs'])),
			'pauseMin' => $in['pauseMin'] ?? $in['pause'] ?? $old['pauseMin'],
			'project' => array_key_exists('project', $in) ? $in['project'] : $old['project'],
			'note' => array_key_exists('note', $in) ? $in['note'] : $old['note'],
		];
		return self::entry($merged, $now, $tz, $projects);
	}

	/**
	 * Absence request.
	 * @return array{ok:bool,errors:list<string>,absence:array{kind:string,fromDay:string,toDay:string,note:?string}}
	 */
	public static function absence(array $in, string $today): array {
		$err = [];
		$kind = trim((string)($in['kind'] ?? ''));
		$from = trim((string)($in['fromDay'] ?? $in['from'] ?? ''));
		$to = trim((string)($in['toDay'] ?? $in['to'] ?? ''));
		if (!in_array($kind, self::KINDS, true)) {
			$err[] = 'kind';
		}
		if (!Dates::validDay($from) || !Dates::validDay($to)) {
			$err[] = 'date';
		} elseif ($to < $from) {
			$err[] = 'order';
		} elseif ($from < Dates::add($today, -90) || $to > Dates::add($today, 365)) {
			$err[] = 'range';
		} elseif (Dates::diff($from, $to) >= 60) {
			$err[] = 'long';
		}
		return ['ok' => $err === [], 'errors' => $err, 'absence' => ['kind' => $kind, 'fromDay' => $from, 'toDay' => $to, 'note' => Sanitize::textOrNull($in['note'] ?? null, 255)]];
	}

	/** @return array{ok:bool,errors:list<string>,reason:string} */
	public static function reason(mixed $v): array {
		$r = Sanitize::text($v ?? '', 500);
		$ok = mb_strlen($r) >= 3;
		return ['ok' => $ok, 'errors' => $ok ? [] : ['reason'], 'reason' => $r];
	}

	public static function mode(mixed $v): bool {
		return is_string($v) && in_array($v, self::MODES, true);
	}

	public static function lang(mixed $v): bool {
		return is_string($v) && in_array($v, Messages::LANGS, true);
	}

	/** weekly target in minutes: 0..6000 (0 = no target) */
	public static function weeklyMin(mixed $v): ?int {
		if (is_string($v) && ctype_digit($v)) {
			$v = (int)$v;
		}
		return (is_int($v) && $v >= 0 && $v <= 6000) ? $v : null;
	}

	/** bit 0 = Monday .. bit 6 = Sunday, at least one day */
	public static function daysMask(mixed $v): ?int {
		if (is_string($v) && ctype_digit($v)) {
			$v = (int)$v;
		}
		return (is_int($v) && $v >= 1 && $v <= 127) ? $v : null;
	}

	/** label of a device: 1..64 characters, default 'Phone' */
	public static function label(mixed $v): string {
		$l = Sanitize::text($v ?? '', 64);
		return $l === '' ? 'Phone' : $l;
	}
}
