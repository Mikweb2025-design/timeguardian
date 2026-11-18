<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Domain;

/** Signed outgoing webhook: header X-TG-Signature = "sha256=" + HMAC-SHA256(secret, "<timestamp>.<body>"). */
final class Webhook {
	public const EVENTS = ['punch', 'violation', 'absence.approved'];

	public static function body(string $event, array $data, int $now): string {
		return json_encode(['event' => $event, 'at' => Dates::iso($now), 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
	}

	public static function signature(string $secret, int $timestamp, string $body): string {
		return 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
	}

	/** receiver side check (used in docs/tests) */
	public static function verify(string $secret, int $timestamp, string $body, string $signature, int $now, int $tolerance = 300): bool {
		if (abs($now - $timestamp) > $tolerance) {
			return false;
		}
		return hash_equals(self::signature($secret, $timestamp, $body), $signature);
	}

	/** only http(s) URLs without credentials */
	public static function validUrl(string $url): bool {
		if ($url === '') {
			return true;
		}
		$p = parse_url($url);
		return is_array($p) && in_array($p['scheme'] ?? '', ['http', 'https'], true) && !empty($p['host']) && !isset($p['user']) && !isset($p['pass']) && strlen($url) <= 500;
	}
}
