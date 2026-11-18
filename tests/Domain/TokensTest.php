<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Tests\Domain;

use OCA\TimeGuardian\Domain\Tokens as T;
use OCA\TimeGuardian\Tests\TestCase;

final class TokensTest extends TestCase {
	public function testGenerate32RandomBytes(): void {
		$a = T::generate();
		$b = T::generate();
		$this->assertTrue(T::wellFormed($a['token']));
		$this->assertSame(64, strlen($a['token']));
		$this->assertTrue($a['token'] !== $b['token']);
		$this->assertSame(hash('sha256', $a['token']), $a['hash']);
		$this->assertTrue($a['hash'] !== $a['token'], 'the stored value is not the token');
	}

	public function testMatches(): void {
		$a = T::generate();
		$b = T::generate();
		$this->assertTrue(T::matches($a['token'], $a['hash']));
		$this->assertFalse(T::matches($b['token'], $a['hash']));
		$this->assertFalse(T::matches($a['hash'], $a['hash']), 'the hash itself is not a valid token');
		$this->assertFalse(T::matches('', $a['hash']));
	}

	public function testWellFormed(): void {
		$this->assertFalse(T::wellFormed(''));
		$this->assertFalse(T::wellFormed(str_repeat('g', 64)));
		$this->assertFalse(T::wellFormed(str_repeat('a', 63)));
		$this->assertFalse(T::wellFormed(str_repeat('A', 64)));
		$this->assertFalse(T::wellFormed(null));
		$this->assertFalse(T::wellFormed(str_repeat('a', 64) . "\n"));
		$this->assertTrue(T::wellFormed(str_repeat('a', 64)));
	}

	public function testDeviceLifecycle(): void {
		$now = 1_800_000_000;
		$d = ['revoked' => 0, 'expires_at' => T::newDeviceExpiry($now), 'last_used' => $now];
		$this->assertSame($now + 180 * 86400, $d['expires_at']);
		$this->assertSame('ok', T::deviceStatus($d, $now));
		$this->assertSame('ok', T::deviceStatus($d, $now + 179 * 86400));
		$this->assertSame('expired', T::deviceStatus($d, $now + 180 * 86400));
		$this->assertSame('expired', T::deviceStatus($d, $now + 400 * 86400));
		$this->assertSame('revoked', T::deviceStatus(['revoked' => 1] + $d, $now));
		$this->assertSame('revoked', T::deviceStatus(['revoked' => true, 'expires_at' => 0], $now), 'revoked wins over expired');
	}

	public function testUsageRenewsButNotEveryRequest(): void {
		$now = 1_800_000_000;
		$d = ['revoked' => 0, 'expires_at' => $now + 10 * 86400, 'last_used' => $now - 10];
		$this->assertNull(T::touch($d, $now), 'used 10 seconds ago: no write');
		$t = T::touch($d, $now + 7200);
		$this->assertSame($now + 7200 + 180 * 86400, $t['expires_at']);
		$this->assertSame($now + 7200, $t['last_used']);
	}

	public function testPairingLifecycle(): void {
		$now = 1_800_000_000;
		$p = ['used' => 0, 'expires_at' => T::newPairExpiry($now)];
		$this->assertSame($now + 300, $p['expires_at']);
		$this->assertSame('ok', T::pairStatus($p, $now + 299));
		$this->assertSame('expired', T::pairStatus($p, $now + 300));
		$this->assertSame('used', T::pairStatus(['used' => 1] + $p, $now));
	}
}
