<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Tests\Domain;

use OCA\TimeGuardian\Domain\PairCode as C;
use OCA\TimeGuardian\Domain\Tokens;
use OCA\TimeGuardian\Tests\TestCase;

final class PairCodeTest extends TestCase {
	public function testGenerateShapeAndAlphabet(): void {
		for ($i = 0; $i < 300; $i++) {
			$c = C::generate();
			$this->assertSame(8, strlen($c));
			$this->assertTrue(preg_match('/^[A-Z2-9]{8}$/D', $c) === 1);
			$this->assertTrue(C::wellFormed($c));
		}
	}

	public function testNoLookAlikes(): void {
		$this->assertSame(32, strlen(C::ALPHABET));
		foreach (['0', 'O', '1', 'I'] as $bad) {
			$this->assertFalse(str_contains(C::ALPHABET, $bad), "alphabet has no $bad");
			$this->assertFalse(C::wellFormed('ABCDEFG' . $bad), "$bad is rejected");
		}
		$seen = '';
		for ($i = 0; $i < 500; $i++) {
			$seen .= C::generate();
		}
		foreach (['0', 'O', '1', 'I'] as $bad) {
			$this->assertFalse(str_contains($seen, $bad), "never generates $bad");
		}
		$this->assertSame(32, count(array_unique(str_split($seen))), 'all 32 characters occur (uniform draw)');
	}

	public function testCodesDiffer(): void {
		$set = [];
		for ($i = 0; $i < 200; $i++) {
			$set[C::generate()] = true;
		}
		$this->assertTrue(count($set) >= 199, 'random, no repeats');
	}

	public function testFormatAndNormalize(): void {
		$this->assertSame('ABCD-EFGH', C::format('ABCDEFGH'));
		foreach (['ABCD-EFGH', 'abcd-efgh', ' abcd efgh ', 'ABCDEFGH', "ab-cd\tef.gh"] as $in) {
			$this->assertSame('ABCDEFGH', C::normalize($in), $in);
			$this->assertTrue(C::wellFormed($in), $in);
		}
		$this->assertSame('', C::normalize(null));
		$this->assertSame('', C::normalize(12345678));
		foreach (['', 'ABCD-EFG', 'ABCD-EFGHJ', 'ABCD-EFG!', str_repeat('a', 64), null, 12345678] as $bad) {
			$this->assertFalse(C::wellFormed($bad), json_encode($bad));
		}
		$this->assertFalse(C::wellFormed(str_repeat('a', 64)), 'a long pairing token is not a code');
		$this->assertFalse(Tokens::wellFormed('ABCDEFGH'), 'a code is not a long token');
	}

	public function testHashIsKeyedAndOnlyOfTheNormalizedCode(): void {
		$h = C::hash('ABCD-EFGH', 'secret');
		$this->assertSame(64, strlen($h));
		$this->assertSame($h, C::hash('abcdefgh', 'secret'), 'typing style does not matter');
		$this->assertTrue($h !== C::hash('ABCD-EFGH', 'other'), 'keyed with the server secret');
		$this->assertTrue($h !== C::hash('ABCD-EFGJ', 'secret'));
		$this->assertFalse(str_contains($h, 'ABCD'), 'only the hash is stored');
	}

	public function testRateLimitBlocksAtTenFailures(): void {
		$this->assertSame(10, C::MAX_FAILS);
		$this->assertSame(3600, C::WINDOW);
		$this->assertFalse(C::blocked(0));
		$this->assertFalse(C::blocked(9));
		$this->assertTrue(C::blocked(10));
		$this->assertTrue(C::blocked(50));
		$this->assertSame(1_800_000_000 - 3600, C::windowStart(1_800_000_000), 'failures older than an hour no longer count');
	}

	public function testVerdictAnswersLikeTheLongToken(): void {
		$now = 1_800_000_000;
		$live = ['used' => 0, 'expires_at' => Tokens::newPairExpiry($now)];
		$this->assertSame('invalid', C::evaluate(null, $now), 'unknown code');
		$this->assertSame('ok', C::evaluate($live, $now + 299));
		$this->assertSame('expired', C::evaluate($live, $now + 300), 'same 5 minutes as the token');
		$this->assertSame('used', C::evaluate(['used' => 1] + $live, $now), 'one time only');
		$this->assertSame('used', C::evaluate(['used' => 1, 'expires_at' => $now - 10], $now), 'used wins over expired');
	}

	public function testLimiterWindowSimulation(): void {
		// simulates the service: every failure is recorded, ten recent ones lock the IP, after an hour it is free again
		$fails = [];
		$now = 1_800_000_000;
		$count = static function (int $t) use (&$fails): int {
			return count(array_filter($fails, static fn (int $f) => $f >= C::windowStart($t)));
		};
		for ($i = 0; $i < 10; $i++) {
			$this->assertFalse(C::blocked($count($now + $i)), "attempt $i allowed");
			$fails[] = $now + $i;
		}
		$this->assertTrue(C::blocked($count($now + 11)), 'eleventh attempt refused');
		$this->assertTrue(C::blocked($count($now + 3599)));
		$this->assertFalse(C::blocked($count($now + 3600 + 10)), 'free again after the window');
	}

	public function testApiDocumentsTheCode(): void {
		$doc = (string)file_get_contents(dirname(__DIR__, 2) . '/docs/API.md');
		$this->assertStringContainsString('"code"', $doc);
		$this->assertStringContainsString('ABCD-EFGH', $doc);
	}
}
