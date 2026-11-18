<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Tests\Domain;

use OCA\TimeGuardian\Domain\Ics;
use OCA\TimeGuardian\Domain\LiveWarnings as L;
use OCA\TimeGuardian\Domain\Webhook;
use OCA\TimeGuardian\Tests\TestCase;

final class IcsWebhookTest extends TestCase {
	public function testAbsenceEvent(): void {
		$ics = Ics::absence(Ics::uid('abs', 3), 'Urlaub, schön; fein', '2026-12-01', '2026-12-03', gmmktime(10, 0, 0, 11, 17, 2026), "Zeile1\nZeile2");
		$this->assertTrue(str_starts_with($ics, "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n"));
		$this->assertTrue(str_ends_with($ics, "END:VCALENDAR\r\n"));
		$this->assertStringContainsString("UID:tg-abs-3@timeguardian.local\r\n", $ics);
		$this->assertStringContainsString("DTSTART;VALUE=DATE:20261201\r\n", $ics);
		$this->assertStringContainsString("DTEND;VALUE=DATE:20261204\r\n", $ics, 'DTEND is exclusive');
		$this->assertStringContainsString("DTSTAMP:20261117T100000Z\r\n", $ics);
		$this->assertStringContainsString('SUMMARY:Urlaub\\, schön\; fein', $ics);
		$this->assertStringContainsString('DESCRIPTION:Zeile1\\nZeile2', $ics);
		$this->assertSame(1, substr_count($ics, 'BEGIN:VEVENT'));
		$this->assertStringNotContainsString("\n\n", str_replace("\r\n", "\n", $ics));
	}

	public function testBlockEventAndFolding(): void {
		$s = gmmktime(7, 0, 0, 11, 17, 2026);
		$ics = Ics::block('x@y', 'Arbeit', $s, $s + 3600, $s);
		$this->assertStringContainsString("DTSTART:20261117T070000Z\r\n", $ics);
		$this->assertStringContainsString("DTEND:20261117T080000Z\r\n", $ics);
		$long = Ics::absence('u@y', str_repeat('ä', 100), '2026-12-01', '2026-12-01', $s);
		foreach (explode("\r\n", $long) as $line) {
			$this->assertTrue(strlen($line) <= 75, 'folded line length ' . strlen($line));
		}
		$unfolded = str_replace("\r\n ", '', $long);
		$this->assertStringContainsString('SUMMARY:' . str_repeat('ä', 100), $unfolded);
	}

	public function testWebhookSignature(): void {
		$body = Webhook::body('punch', ['uid' => 'anna'], 1763362920);
		$this->assertSame('{"event":"punch","at":"2025-11-17T07:02:00Z","data":{"uid":"anna"}}', $body);
		$sig = Webhook::signature('s3cret', 1763362920, $body);
		$this->assertSame('sha256=' . hash_hmac('sha256', '1763362920.' . $body, 's3cret'), $sig);
		$this->assertTrue(Webhook::verify('s3cret', 1763362920, $body, $sig, 1763362920 + 10));
		$this->assertFalse(Webhook::verify('other', 1763362920, $body, $sig, 1763362920));
		$this->assertFalse(Webhook::verify('s3cret', 1763362920, $body . ' ', $sig, 1763362920));
		$this->assertFalse(Webhook::verify('s3cret', 1763362920, $body, $sig, 1763362920 + 301), 'replay window');
	}

	public function testWebhookUrls(): void {
		$this->assertTrue(Webhook::validUrl(''));
		$this->assertTrue(Webhook::validUrl('https://example.org/hook'));
		$this->assertTrue(Webhook::validUrl('http://n8n.local:5678/webhook/x'));
		$this->assertFalse(Webhook::validUrl('ftp://example.org'));
		$this->assertFalse(Webhook::validUrl('javascript:alert(1)'));
		$this->assertFalse(Webhook::validUrl('https://user:pw@example.org/'));
		$this->assertFalse(Webhook::validUrl('example.org/hook'));
	}

	public function testLiveWarnings(): void {
		$now = 1_800_000_000;
		$this->assertSame([], L::evaluate('pause', $now - 7200, $now, 600, 0));
		$this->assertSame([], L::evaluate('working', $now - 3600, $now, 60, 0));
		$this->assertSame([], L::evaluate('working', $now - 3600, $now, 344, 0), '5:44 is fine');
		$this->assertSame(['live_break'], L::evaluate('working', $now - 3600, $now, 345, 0));
		$this->assertSame([], L::evaluate('working', $now - 3600, $now, 400, 30), 'break taken');
		$this->assertSame(['live_break'], L::evaluate('working', $now - 3600, $now, 345, 29));
		$this->assertSame(['live_day10'], L::evaluate('working', $now - 3600, $now, 570, 45));
		$this->assertSame(['live_day10', 'live_break'], L::evaluate('working', $now - 3600, $now, 570, 30), 'above 9 h 45 min break are needed');
		$this->assertSame(['live_forgot'], L::evaluate('working', $now - 720 * 60, $now, 300, 30));
		$this->assertSame([], L::evaluate('working', $now - 719 * 60, $now, 300, 30));
		$this->assertSame(['live_day10'], L::pending(['live_day10', 'live_break'], ['live_break']));
		$this->assertSame([], L::pending(['live_day10'], ['live_day10']));
	}
}
