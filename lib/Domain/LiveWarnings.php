<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Domain;

/** Warnings for someone who is working right now. */
final class LiveWarnings {
	public const DAY10 = 'live_day10';
	public const BREAK = 'live_break';
	public const FORGOT = 'live_forgot';

	/** net minutes at which the 10 h limit warning fires (9 h 30) */
	public const DAY10_AT = 570;
	/** net minutes at which the missing break warning fires (5 h 45) */
	public const BREAK_AT = 345;
	/** continuous working time after which a forgotten clock-out is suspected (12 h) */
	public const FORGOT_AT = 720;

	/**
	 * @param int $sinceTs start of the current working stretch (last in/resume)
	 * @param int $netMin net minutes worked today including the running block
	 * @param int $pauseMin break minutes today (manual + gaps of 15 min or more)
	 * @return list<string>
	 */
	public static function evaluate(string $state, int $sinceTs, int $now, int $netMin, int $pauseMin): array {
		if ($state !== PunchMachine::WORKING) {
			return [];
		}
		$k = [];
		if ($netMin >= self::DAY10_AT) {
			$k[] = self::DAY10;
		}
		if ($netMin >= self::BREAK_AT && $pauseMin < ($netMin > 540 ? 45 : 30)) {
			$k[] = self::BREAK;
		}
		if (intdiv($now - $sinceTs, 60) >= self::FORGOT_AT) {
			$k[] = self::FORGOT;
		}
		return $k;
	}

	/**
	 * @param list<string> $kinds @param list<string> $sentToday
	 * @return list<string>
	 */
	public static function pending(array $kinds, array $sentToday): array {
		return array_values(array_diff($kinds, $sentToday));
	}
}
