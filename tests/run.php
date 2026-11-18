<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Usage: php tests/run.php [filter]
 * Runs every tests/**\/*Test.php (methods starting with "test"). No dependencies.
 */

require __DIR__ . '/bootstrap.php';

use OCA\TimeGuardian\Tests\TestCase;

$filter = $argv[1] ?? '';
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
	if (str_ends_with($f->getFilename(), 'Test.php')) {
		$files[] = $f->getPathname();
	}
}
sort($files);
$failed = 0;
$tests = 0;
foreach ($files as $file) {
	$rel = substr($file, strlen(__DIR__) + 1, -4);
	$class = 'OCA\\TimeGuardian\\Tests\\' . str_replace('/', '\\', $rel);
	if ($filter !== '' && !str_contains($class, $filter)) {
		continue;
	}
	$obj = new $class();
	foreach (get_class_methods($obj) as $m) {
		if (!str_starts_with($m, 'test')) {
			continue;
		}
		$tests++;
		$before = TestCase::$checks;
		try {
			$obj->$m();
			echo '.';
		} catch (Throwable $e) {
			$failed++;
			echo "\nFAIL {$class}::{$m}: " . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ")\n";
		}
	}
}
echo "\n" . ($failed === 0 ? 'OK' : 'FAILED') . ": {$tests} tests, " . TestCase::$checks . " checks, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
