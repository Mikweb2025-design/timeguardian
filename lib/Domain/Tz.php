<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Domain;

/** Conversions between UTC instants (unix seconds) and local wall clock days/minutes in a configured zone. */
final class Tz {
	public const DEFAULT = 'Europe/Berlin';

	public static function zone(?string $name): \DateTimeZone {
		try {
			return new \DateTimeZone($name !== null && $name !== '' ? $name : self::DEFAULT);
		} catch (\Throwable) {
			return new \DateTimeZone(self::DEFAULT);
		}
	}

	public static function isValidName(string $name): bool {
		return in_array($name, \DateTimeZone::listIdentifiers(), true);
	}

	/** local calendar day of an instant */
	public static function day(int $ts, \DateTimeZone $tz): string {
		return (new \DateTimeImmutable('@' . $ts))->setTimezone($tz)->format('Y-m-d');
	}

	/** instant of the local midnight that starts $day */
	public static function midnight(string $day, \DateTimeZone $tz): int {
		return (new \DateTimeImmutable($day . ' 00:00:00', $tz))->getTimestamp();
	}

	/** instant of a local wall clock time ($min may exceed 1439: following days) */
	public static function wall(string $day, int $min, \DateTimeZone $tz): int {
		$d = new \DateTimeImmutable($day . ' 00:00:00', $tz);
		if ($min >= 1440) {
			$d = $d->modify('+' . intdiv($min, 1440) . ' day');
			$min %= 1440;
		}
		return $d->setTime(intdiv($min, 60), $min % 60)->getTimestamp();
	}

	/** wall clock 'HH:MM' of an instant */
	public static function hm(int $ts, \DateTimeZone $tz): string {
		return (new \DateTimeImmutable('@' . $ts))->setTimezone($tz)->format('H:i');
	}

	/** elapsed minutes between the local midnight of $day and the instant (may be > 1440, never negative) */
	public static function offsetMin(int $ts, string $day, \DateTimeZone $tz): int {
		return max(0, intdiv($ts - self::midnight($day, $tz), 60));
	}
}
