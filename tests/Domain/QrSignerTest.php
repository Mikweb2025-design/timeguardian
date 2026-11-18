<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Tests\Domain;

use OCA\TimeGuardian\Domain\QrSigner as Q;
use OCA\TimeGuardian\Tests\TestCase;

final class QrSignerTest extends TestCase {
	private const SECRET = 's3cret-instance-secret';
	private const NOW = 1763362920;

	public function testCodeFormatAndWindow(): void {
		$c = Q::code(self::SECRET, self::NOW);
		$this->assertTrue(preg_match('/^\d+\.[0-9a-f]{32}$/', $c) === 1);
		$this->assertSame(intdiv(self::NOW, 60), (int)explode('.', $c)[0]);
		$this->assertSame(substr(hash_hmac('sha256', 'tg-qr|' . intdiv(self::NOW, 60), self::SECRET), 0, 32), explode('.', $c)[1], 'documented construction');
	}

	public function testCurrentAndPreviousWindowAreAccepted(): void {
		$c = Q::code(self::SECRET, self::NOW);
		$this->assertTrue(Q::verify(self::SECRET, $c, self::NOW));
		$this->assertTrue(Q::verify(self::SECRET, $c, self::NOW + 59 - (self::NOW % 60)), 'last second of the window');
		$this->assertTrue(Q::verify(self::SECRET, $c, self::NOW - (self::NOW % 60) + 60 + 30), 'previous window still accepted');
	}

	public function testOlderWindowsAndFutureWindowsAreRejected(): void {
		$c = Q::code(self::SECRET, self::NOW);
		$this->assertFalse(Q::verify(self::SECRET, $c, self::NOW + 120), 'two windows later');
		$this->assertFalse(Q::verify(self::SECRET, $c, self::NOW - 60), 'window from the future');
		$this->assertFalse(Q::verify(self::SECRET, $c, self::NOW + 3600));
	}

	public function testTamperingIsRejected(): void {
		$c = Q::code(self::SECRET, self::NOW);
		[$w, $mac] = explode('.', $c);
		$flip = $mac;
		$flip[0] = $flip[0] === 'a' ? 'b' : 'a';
		$this->assertFalse(Q::verify(self::SECRET, $w . '.' . $flip, self::NOW));
		$this->assertFalse(Q::verify(self::SECRET, ((int)$w - 1) . '.' . $mac, self::NOW), 'mac of another window');
		$this->assertFalse(Q::verify('other-secret', $c, self::NOW));
		$this->assertFalse(Q::verify(self::SECRET, $w . '.' . substr($mac, 0, 31), self::NOW));
		$this->assertFalse(Q::verify(self::SECRET, $w . '.' . strtoupper($mac), self::NOW));
		$this->assertFalse(Q::verify(self::SECRET, '', self::NOW));
		$this->assertFalse(Q::verify(self::SECRET, "$w.$mac ", self::NOW));
		$this->assertFalse(Q::verify(self::SECRET, "$w.$mac\n", self::NOW), 'trailing newline');
		$this->assertFalse(Q::verify(self::SECRET, '..', self::NOW));
		$this->assertFalse(Q::verify('', Q::code('', self::NOW), self::NOW), 'empty secret never validates');
	}

	public function testExpiresIn(): void {
		$this->assertSame(60, Q::expiresIn(1763362920 - (1763362920 % 60)));
		$this->assertSame(1, Q::expiresIn(1763362920 - (1763362920 % 60) + 59));
	}

	public function testRotationEveryMinute(): void {
		$seen = [];
		for ($t = self::NOW; $t < self::NOW + 600; $t += 60) {
			$seen[Q::code(self::SECRET, $t)] = true;
		}
		$this->assertSame(10, count($seen));
	}

	public function testSecretIsRandom(): void {
		$a = Q::newSecret();
		$b = Q::newSecret();
		$this->assertTrue(strlen($a) === 64 && $a !== $b);
	}

	public function testCrossLanguageVector(): void {
		// computed independently with node's crypto.createHmac('sha256', secret).update('tg-qr|<window>').digest('hex').slice(0,32)
		$v = $this->vectors('crypto');
		foreach ($v['qr'] as $c) {
			$this->assertSame($c['code'], Q::code($c['secret'], $c['now']));
		}
	}

	public function testRfcHmacAndSha(): void {
		foreach ($this->vectors('crypto')['hmac'] as $c) {
			$key = isset($c['keyHex']) ? hex2bin($c['keyHex']) : $c['key'];
			$this->assertSame($c['hex'], hash_hmac('sha256', $c['data'], $key));
		}
		foreach ($this->vectors('crypto')['sha256'] as [$in, $hex]) {
			$this->assertSame($hex, hash('sha256', $in));
		}
	}
}
