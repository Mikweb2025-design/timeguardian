<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Domain;

/** Minimal iCalendar writer (RFC 5545) for absences and work blocks. */
final class Ics {
	public const PRODID = '-//TimeGuardian//Nextcloud//EN';
	public const UID_DOMAIN = 'timeguardian.local';

	public static function esc(string $s): string {
		$s = str_replace('\\', '\\\\', $s);
		$s = str_replace([';', ','], ['\;', '\,'], $s);
		return str_replace(["\r\n", "\n", "\r"], '\n', $s);
	}

	/** folds a content line at 75 octets */
	public static function fold(string $line): string {
		if (strlen($line) <= 75) {
			return $line;
		}
		$out = '';
		$cur = '';
		foreach (mb_str_split($line) as $ch) {
			if (strlen($cur) + strlen($ch) > ($out === '' ? 75 : 74)) {
				$out .= ($out === '' ? '' : "\r\n ") . $cur;
				$cur = '';
			}
			$cur .= $ch;
		}
		return $out . ($out === '' ? '' : "\r\n ") . $cur;
	}

	public static function uid(string $kind, int $id): string {
		return 'tg-' . $kind . '-' . $id . '@' . self::UID_DOMAIN;
	}

	/** @param list<string> $lines */
	private static function wrap(array $lines): string {
		$all = array_merge(['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:' . self::PRODID, 'CALSCALE:GREGORIAN'], $lines, ['END:VCALENDAR']);
		return implode("\r\n", array_map([self::class, 'fold'], $all)) . "\r\n";
	}

	/** all-day event from..to inclusive (DTEND is exclusive in iCalendar) */
	public static function absence(string $uid, string $summary, string $fromDay, string $toDay, int $nowTs, string $description = ''): string {
		$d = static fn (string $s): string => str_replace('-', '', $s);
		$lines = ['BEGIN:VEVENT', 'UID:' . $uid, 'DTSTAMP:' . gmdate('Ymd\THis\Z', $nowTs),
			'DTSTART;VALUE=DATE:' . $d($fromDay), 'DTEND;VALUE=DATE:' . $d(Dates::add($toDay, 1)),
			'SUMMARY:' . self::esc($summary)];
		if ($description !== '') {
			$lines[] = 'DESCRIPTION:' . self::esc($description);
		}
		array_push($lines, 'TRANSP:TRANSPARENT', 'CATEGORIES:TimeGuardian', 'END:VEVENT');
		return self::wrap($lines);
	}

	/** timed event in UTC */
	public static function block(string $uid, string $summary, int $startTs, int $endTs, int $nowTs, string $description = ''): string {
		$lines = ['BEGIN:VEVENT', 'UID:' . $uid, 'DTSTAMP:' . gmdate('Ymd\THis\Z', $nowTs),
			'DTSTART:' . gmdate('Ymd\THis\Z', $startTs), 'DTEND:' . gmdate('Ymd\THis\Z', max($endTs, $startTs + 60)),
			'SUMMARY:' . self::esc($summary)];
		if ($description !== '') {
			$lines[] = 'DESCRIPTION:' . self::esc($description);
		}
		array_push($lines, 'TRANSP:TRANSPARENT', 'CATEGORIES:TimeGuardian', 'END:VEVENT');
		return self::wrap($lines);
	}
}
