<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Domain;

/** Device and pairing tokens: 32 random bytes, only the SHA-256 hash is ever stored, compared in constant time. */
final class Tokens {
	public const DEVICE_DAYS = 180;
	public const PAIR_TTL = 300;
	/** extend a device token at most this often (seconds), saves writes */
	public const TOUCH_EVERY = 3600;

	/** @return array{token:string,hash:string} */
	public static function generate(): array {
		$t = bin2hex(random_bytes(32));
		return ['token' => $t, 'hash' => self::hash($t)];
	}

	public static function hash(string $token): string {
		return hash('sha256', $token);
	}

	public static function wellFormed(mixed $t): bool {
		return is_string($t) && preg_match('/^[0-9a-f]{64}$/D', $t) === 1;
	}

	public static function matches(string $token, string $storedHash): bool {
		return hash_equals($storedHash, self::hash($token));
	}

	// ---- device lifecycle ------------------------------------------------------------------------------------------

	public static function newDeviceExpiry(int $now): int {
		return $now + self::DEVICE_DAYS * 86400;
	}

	/**
	 * @param array{revoked:int|bool,expires_at:int} $device
	 * @return 'ok'|'revoked'|'expired'
	 */
	public static function deviceStatus(array $device, int $now): string {
		if (!empty($device['revoked'])) {
			return 'revoked';
		}
		return (int)$device['expires_at'] <= $now ? 'expired' : 'ok';
	}

	/**
	 * Usage renews the device token. Returns null when nothing needs to be written.
	 * @return array{last_used:int,expires_at:int}|null
	 */
	public static function touch(array $device, int $now): ?array {
		if ($now - (int)($device['last_used'] ?? 0) < self::TOUCH_EVERY) {
			return null;
		}
		return ['last_used' => $now, 'expires_at' => self::newDeviceExpiry($now)];
	}

	// ---- pairing lifecycle -----------------------------------------------------------------------------------------

	public static function newPairExpiry(int $now): int {
		return $now + self::PAIR_TTL;
	}

	/** @return 'ok'|'used'|'expired' */
	public static function pairStatus(array $pair, int $now): string {
		if (!empty($pair['used'])) {
			return 'used';
		}
		return (int)$pair['expires_at'] <= $now ? 'expired' : 'ok';
	}
}
