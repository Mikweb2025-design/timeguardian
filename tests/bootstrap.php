<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Test bootstrap without Nextcloud: only lib/Domain (no OCP dependencies) is loaded.
 */

spl_autoload_register(static function (string $class): void {
	$map = ['OCA\\TimeGuardian\\Tests\\' => __DIR__ . '/', 'OCA\\TimeGuardian\\' => dirname(__DIR__) . '/lib/'];
	foreach ($map as $prefix => $dir) {
		if (str_starts_with($class, $prefix)) {
			$file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
			if (is_file($file)) {
				require $file;
			}
			return;
		}
	}
});
