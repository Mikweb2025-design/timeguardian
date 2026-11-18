<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Domain;

/**
 * Which configuration values a manager (chef) may change in the app. Everything uncritical is open to managers;
 * the three group names decide who is a manager at all, so only Nextcloud administrators may change them
 * (managers can read them).
 */
final class ConfigAccess {
	/** keys of the configuration that stay reserved to Nextcloud administrators */
	public const ADMIN_ONLY = ['chefGroup', 'teamGroup', 'kioskGroup'];

	/** @return list<string> keys of $in that a manager may not write */
	public static function adminOnlyKeys(array $in): array {
		return array_values(array_intersect(array_keys($in), self::ADMIN_ONLY));
	}

	/** $in without the admin-only keys */
	public static function stripAdminOnly(array $in): array {
		return array_diff_key($in, array_flip(self::ADMIN_ONLY));
	}

	/** may $actor (manager or admin) write the keys of $in? Admins may write everything, managers the rest. */
	public static function mayWrite(array $in, bool $isAdmin): bool {
		return $isAdmin || self::adminOnlyKeys($in) === [];
	}
}
