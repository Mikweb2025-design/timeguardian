<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Tests;

use OCA\TimeGuardian\Domain\TgException;

/** Tiny PHPUnit-like base class so the tests run without composer, phpunit or Nextcloud. */
abstract class TestCase {
	public static int $checks = 0;

	protected function fail(string $msg): never {
		throw new \RuntimeException($msg);
	}

	protected function assertTrue(mixed $v, string $msg = ''): void {
		self::$checks++;
		if ($v !== true) {
			$this->fail('expected true' . ($msg !== '' ? ': ' . $msg : ''));
		}
	}

	protected function assertFalse(mixed $v, string $msg = ''): void {
		self::$checks++;
		if ($v !== false) {
			$this->fail('expected false' . ($msg !== '' ? ': ' . $msg : ''));
		}
	}

	protected function assertSame(mixed $expected, mixed $actual, string $msg = ''): void {
		self::$checks++;
		if ($expected !== $actual) {
			$this->fail('expected ' . json_encode($expected) . ' got ' . json_encode($actual) . ($msg !== '' ? ' (' . $msg . ')' : ''));
		}
	}

	protected function assertEquals(mixed $expected, mixed $actual, string $msg = ''): void {
		self::$checks++;
		if ($expected != $actual) {
			$this->fail('expected ' . json_encode($expected) . ' got ' . json_encode($actual) . ($msg !== '' ? ' (' . $msg . ')' : ''));
		}
	}

	protected function assertNull(mixed $v, string $msg = ''): void {
		$this->assertSame(null, $v, $msg);
	}

	protected function assertCount(int $n, array $a, string $msg = ''): void {
		$this->assertSame($n, count($a), $msg !== '' ? $msg : 'count');
	}

	protected function assertContains(mixed $needle, array $hay, string $msg = ''): void {
		self::$checks++;
		if (!in_array($needle, $hay, true)) {
			$this->fail(json_encode($needle) . ' not in ' . json_encode(mb_substr($hay, 0, 200)) . ($msg !== '' ? ' (' . $msg . ')' : ''));
		}
	}

	protected function assertNotContains(mixed $needle, array $hay, string $msg = ''): void {
		self::$checks++;
		if (in_array($needle, $hay, true)) {
			$this->fail(json_encode($needle) . ' unexpectedly in ' . json_encode($hay) . ($msg !== '' ? ' (' . $msg . ')' : ''));
		}
	}

	protected function assertStringContainsString(string $needle, string $hay, string $msg = ''): void {
		self::$checks++;
		if (!str_contains($hay, $needle)) {
			$this->fail(json_encode($needle) . ' not in ' . json_encode($hay) . ($msg !== '' ? ' (' . $msg . ')' : ''));
		}
	}

	protected function assertStringNotContainsString(string $needle, string $hay, string $msg = ''): void {
		self::$checks++;
		if (str_contains($hay, $needle)) {
			$this->fail(json_encode($needle) . ' unexpectedly in ' . json_encode($hay) . ($msg !== '' ? ' (' . $msg . ')' : ''));
		}
	}

	/** the callable must throw a TgException with this code */
	protected function assertTgError(string $code, callable $fn, string $msg = ''): void {
		self::$checks++;
		try {
			$fn();
		} catch (TgException $e) {
			if ($e->errorCode() !== $code) {
				$this->fail('expected error ' . $code . ' got ' . $e->errorCode() . ($msg !== '' ? ' (' . $msg . ')' : ''));
			}
			return;
		}
		$this->fail('expected error ' . $code . ', nothing thrown' . ($msg !== '' ? ' (' . $msg . ')' : ''));
	}

	/** @return array decoded JSON file of tests/vectors */
	protected function vectors(string $name): array {
		$j = json_decode((string)file_get_contents(__DIR__ . '/vectors/' . $name . '.json'), true, 512, JSON_THROW_ON_ERROR);
		return $j;
	}
}
