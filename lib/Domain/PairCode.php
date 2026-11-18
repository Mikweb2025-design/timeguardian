<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Domain;

/**
 * Short one-time pairing code, typed by hand into the installed app (iPhone home-screen apps have their own storage,
 * so the pairing QR scanned with the camera - which always opens Safari - cannot reach them).
 *
 * 8 characters from a 32 character alphabet without the look-alikes 0/O and 1/I (40 bit), shown as ABCD-EFGH.
 * Only a keyed hash is stored. Same lifetime as the long token (Tokens::PAIR_TTL).
 */
final class PairCode {
	public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
	public const LENGTH = 8;
	/** wrong attempts allowed per IP address and window before the code path is locked */
	public const MAX_FAILS = 10;
	public const WINDOW = 3600;

	public static function generate(): string {
		$max = strlen(self::ALPHABET) - 1;
		$c = '';
		for ($i = 0; $i < self::LENGTH; $i++) {
			$c .= self::ALPHABET[random_int(0, $max)];
		}
		return $c;
	}

	/** ABCD-EFGH */
	public static function format(string $code): string {
		return substr($code, 0, 4) . '-' . substr($code, 4);
	}

	/** Upper case, without spaces, dashes and dots: what a person typed -> canonical form (may still be invalid). */
	public static function normalize(mixed $input): string {
		if (!is_string($input)) {
			return '';
		}
		return strtoupper((string)preg_replace('/[\s\-._]+/u', '', $input));
	}

	public static function wellFormed(mixed $input): bool {
		return preg_match('/^[ABCDEFGHJKLMNPQRSTUVWXYZ2-9]{8}$/D', self::normalize($input)) === 1;
	}

	/** Keyed hash (the key is a server secret), so that a leaked table cannot be searched for codes. */
	public static function hash(string $input, string $secret): string {
		return hash_hmac('sha256', self::normalize($input), 'tg-pair-code|' . $secret);
	}

	/** true when this many recent wrong attempts mean: refuse further code attempts */
	public static function blocked(int $recentFailures): bool {
		return $recentFailures >= self::MAX_FAILS;
	}

	public static function windowStart(int $now): int {
		return $now - self::WINDOW;
	}

	/**
	 * Verdict for the pairing row found by the code hash (null = no such code).
	 * @param array{used:int|bool,expires_at:int}|null $pair
	 * @return 'ok'|'invalid'|'expired'|'used'
	 */
	public static function evaluate(?array $pair, int $now): string {
		if ($pair === null) {
			return 'invalid';
		}
		return Tokens::pairStatus($pair, $now);
	}
}
