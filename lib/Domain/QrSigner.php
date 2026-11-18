<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Domain;

/**
 * Rotating kiosk code: "<window>.<mac>" with window = floor(unix time / 60) and
 * mac = first 32 hex chars of HMAC-SHA256(secret, "tg-qr|<window>"). The server accepts the current and the previous window.
 */
final class QrSigner {
	public const WINDOW_SEC = 60;
	public const MAC_LEN = 32;

	public static function window(int $now): int {
		return intdiv($now, self::WINDOW_SEC);
	}

	public static function mac(string $secret, int $window): string {
		return substr(hash_hmac('sha256', 'tg-qr|' . $window, $secret), 0, self::MAC_LEN);
	}

	public static function code(string $secret, int $now): string {
		$w = self::window($now);
		return $w . '.' . self::mac($secret, $w);
	}

	public static function expiresIn(int $now): int {
		return self::WINDOW_SEC - ($now % self::WINDOW_SEC);
	}

	/** @return array{0:int,1:string}|null */
	public static function parse(string $code): ?array {
		if (!preg_match('/^(\d{1,12})\.([0-9a-f]{' . self::MAC_LEN . '})$/D', $code, $m)) {
			return null;
		}
		return [(int)$m[1], $m[2]];
	}

	public static function verify(string $secret, string $code, int $now): bool {
		if ($secret === '') {
			return false;
		}
		$p = self::parse($code);
		if ($p === null) {
			return false;
		}
		$cur = self::window($now);
		if ($p[0] !== $cur && $p[0] !== $cur - 1) {
			return false;
		}
		return hash_equals(self::mac($secret, $p[0]), $p[1]);
	}

	public static function newSecret(): string {
		return bin2hex(random_bytes(32));
	}
}
