<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Tests\Domain;

use OCA\TimeGuardian\Domain\Dates;
use OCA\TimeGuardian\Domain\Tz;
use OCA\TimeGuardian\Tests\TestCase;

final class DatesTest extends TestCase {
	public function testSharedVectors(): void {
		$v = $this->vectors('dates');
		foreach ($v['dow'] as [$d, $n]) {
			$this->assertSame($n, Dates::dow($d), $d);
		}
		foreach ($v['monday'] as [$d, $m]) {
			$this->assertSame($m, Dates::monday($d), $d);
		}
		foreach ($v['isoWeek'] as [$d, $y, $w]) {
			$this->assertSame(['year' => $y, 'week' => $w], Dates::isoWeek($d), $d);
		}
		foreach ($v['parseHM'] as [$s, $m]) {
			$this->assertSame($m, Dates::parseHM($s), (string)$s);
		}
		foreach ($v['validDay'] as [$s, $ok]) {
			$this->assertSame($ok, Dates::validDay($s), $s);
		}
		foreach ($v['fmtH'] as [$min, $lang, $t]) {
			$this->assertSame($t, Dates::fmtH($min, $lang));
		}
		foreach ($v['fmtHM'] as [$min, $t]) {
			$this->assertSame($t, Dates::fmtHM($min));
		}
	}

	public function testArithmetic(): void {
		$this->assertSame('2026-12-01', Dates::add('2026-11-30', 1));
		$this->assertSame('2024-02-29', Dates::add('2024-02-28', 1));
		$this->assertSame('2026-11-10', Dates::add('2026-11-17', -7));
		$this->assertSame(7, Dates::diff('2026-11-10', '2026-11-17'));
		$this->assertCount(3, Dates::range('2026-11-10', '2026-11-12'));
		$this->assertSame(['2026-02-01', '2026-02-28'], Dates::monthRange('2026-02'));
		$this->assertSame(['2024-02-01', '2024-02-29'], Dates::monthRange('2024-02'));
		$this->assertNull(Dates::monthRange('2026-13'));
		$this->assertNull(Dates::monthRange('26-01'));
	}

	public function testIso(): void {
		$this->assertSame('2026-11-17T07:02:00Z', Dates::iso(Dates::parseIso('2026-11-17T08:02:00+01:00')));
		$this->assertSame(1763362920, Dates::parseIso('2025-11-17T07:02:00Z'));
		$this->assertNull(Dates::parseIso('2026-11-17 07:02'));
		$this->assertNull(Dates::parseIso('08:00'));
	}

	public function testTimeZoneLocalDayAcrossMidnightAndDst(): void {
		$tz = Tz::zone('Europe/Berlin');
		// 23:30 UTC on Nov 17 is 00:30 on Nov 18 in Berlin (winter, +1)
		$ts = gmmktime(23, 30, 0, 11, 17, 2026);
		$this->assertSame('2026-11-18', Tz::day($ts, $tz));
		$this->assertSame('00:30', Tz::hm($ts, $tz));
		// summer time: 22:30 UTC on Jul 1 is 00:30 on Jul 2
		$this->assertSame('2026-07-02', Tz::day(gmmktime(22, 30, 0, 7, 1, 2026), $tz));
		// wall clock round trip
		$w = Tz::wall('2026-07-01', 8 * 60, $tz);
		$this->assertSame(gmmktime(6, 0, 0, 7, 1, 2026), $w);
		$this->assertSame('08:00', Tz::hm($w, $tz));
		// DST switch day (26 Oct 2026, 25 hours): elapsed minutes since local midnight
		$m = Tz::midnight('2026-10-25', $tz);
		$this->assertSame(gmmktime(22, 0, 0, 10, 24, 2026), $m);
		$this->assertSame(10 * 60, Tz::offsetMin($m + 600 * 60, '2026-10-25', $tz));
		// unknown zone falls back
		$this->assertSame('Europe/Berlin', Tz::zone('Nope/Nowhere')->getName());
		$this->assertTrue(Tz::isValidName('Europe/Rome'));
		$this->assertFalse(Tz::isValidName('Mars/Olympus'));
	}
}
