<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Domain;

final class Sanitize {
	/** single line plain text: tags and control characters removed, whitespace collapsed, cut to $max characters */
	public static function text(mixed $s, int $max): string {
		$s = is_scalar($s) ? (string)$s : '';
		$t = @preg_replace('/<[^>]*>/u', ' ', $s);
		if ($t === null) {
			$t = '';
		}
		$t = preg_replace('/[\x00-\x1f\x7f<>]/u', ' ', $t) ?? '';
		$t = preg_replace('/\s+/u', ' ', $t) ?? '';
		return mb_substr(trim($t), 0, $max);
	}

	/** like text() but may be empty -> null */
	public static function textOrNull(mixed $s, int $max): ?string {
		$t = self::text($s, $max);
		return $t === '' ? null : $t;
	}
}
