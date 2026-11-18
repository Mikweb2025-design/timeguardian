<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Tests\Domain;

use OCA\TimeGuardian\Domain\Balance;
use OCA\TimeGuardian\Domain\Dates;
use OCA\TimeGuardian\Tests\TestCase;

final class BalanceTest extends TestCase {
	public function testTargetPerDay(): void {
		$this->assertSame(480, Balance::target('2026-11-17', 2400, 31, []));
		$this->assertSame(0, Balance::target('2026-11-21', 2400, 31, []), 'Saturday is not a working day');
		$this->assertSame(0, Balance::target('2026-11-17', 2400, 31, ['2026-11-17' => 'x']), 'holiday');
		$this->assertSame(600, Balance::target('2026-11-17', 1800, 0b0000111, []), 'three days a week');
		$this->assertSame(0, Balance::target('2026-11-19', 1800, 0b0000111, []), 'Thursday of a Mo-Wed part timer');
		$this->assertSame(0, Balance::target('2026-11-17', 2400, 0, []));
		$this->assertSame(380, Balance::target('2026-11-17', 1900, 31, []));
		$this->assertSame(2400, Balance::target('2026-11-21', 2400, 0b0100000, []), 'Saturday as the only working day');
	}

	public function testWorkdays(): void {
		$this->assertSame(5, Balance::workdays(31));
		$this->assertSame(7, Balance::workdays(127));
		$this->assertSame(1, Balance::workdays(64));
	}

	public function testAbsenceCredit(): void {
		$hol = [];
		$x = Balance::day('2026-11-17', 2400, 31, $hol, ['kind' => 'vacation', 'id' => 1], 0);
		$this->assertSame(['sollMin' => 480, 'netMin' => 0, 'creditMin' => 480, 'deltaMin' => 0], $x, 'vacation counts as fulfilled');
		$x = Balance::day('2026-11-17', 2400, 31, $hol, ['kind' => 'sick', 'id' => 1], 0);
		$this->assertSame(0, $x['deltaMin']);
		$x = Balance::day('2026-11-17', 2400, 31, $hol, ['kind' => 'trip', 'id' => 1], 0);
		$this->assertSame(0, $x['deltaMin']);
		$x = Balance::day('2026-11-17', 2400, 31, $hol, ['kind' => 'flex', 'id' => 1], 0);
		$this->assertSame(-480, $x['deltaMin'], 'flex time consumes the balance');
		$x = Balance::day('2026-11-17', 2400, 31, $hol, null, 540);
		$this->assertSame(60, $x['deltaMin']);
		$x = Balance::day('2026-11-21', 2400, 31, $hol, ['kind' => 'vacation', 'id' => 1], 0);
		$this->assertSame(0, $x['creditMin'], 'no credit on a non working day');
	}

	public function testExpandAbsences(): void {
		$m = Balance::expandAbsences([
			['id' => 1, 'kind' => 'flex', 'fromDay' => '2026-11-16', 'toDay' => '2026-11-18'],
			['id' => 2, 'kind' => 'sick', 'fromDay' => '2026-11-18', 'toDay' => '2026-11-19'],
		], '2026-11-01', '2026-11-30');
		$this->assertSame('flex', $m['2026-11-17']['kind']);
		$this->assertSame('sick', $m['2026-11-18']['kind'], 'credit kinds win');
		$this->assertSame('sick', $m['2026-11-19']['kind']);
		$clip = Balance::expandAbsences([['id' => 1, 'kind' => 'vacation', 'fromDay' => '2026-10-30', 'toDay' => '2026-11-02']], '2026-11-01', '2026-11-30');
		$this->assertSame(['2026-11-01', '2026-11-02'], array_keys($clip));
	}

	public function testRunningBalance(): void {
		// Mon 16.11 .. Fri 20.11: 9h, 8h, 7h, off (vacation), 8h30. Balance through Friday: +60 +0 -60 +0 +30 = +30
		$net = ['2026-11-16' => 540, '2026-11-17' => 480, '2026-11-18' => 420, '2026-11-20' => 510];
		$abs = [['id' => 1, 'kind' => 'vacation', 'fromDay' => '2026-11-19', 'toDay' => '2026-11-19']];
		$a = Balance::account('2026-11-16', '2026-11-20', 2400, 31, 'BE', $net, $abs);
		$this->assertSame(30, $a['balanceMin']);
		$this->assertSame('2026-11-20', $a['asOf']);
		$this->assertCount(1, $a['history']);
		$this->assertSame(['ym' => '2026-11', 'sollMin' => 2400, 'istMin' => 1950, 'creditMin' => 480, 'deltaMin' => 30, 'balanceMin' => 30], $a['history'][0]);
	}

	public function testHolidayHasNoTarget(): void {
		// Fri 1 May 2026 is a holiday everywhere; working on it is pure plus, not working is neutral
		$a = Balance::account('2026-05-01', '2026-05-01', 2400, 31, 'BY', [], []);
		$this->assertSame(0, $a['balanceMin']);
		$b = Balance::account('2026-05-01', '2026-05-01', 2400, 31, 'BY', ['2026-05-01' => 120], []);
		$this->assertSame(120, $b['balanceMin']);
		// Good Friday 2026-04-03 and Easter Monday 2026-04-06: a normal week with 2 holidays has target 3 x 480
		$w = Balance::account('2026-03-30', '2026-04-05', 2400, 31, 'BE', [], []);
		$this->assertSame(-(4 * 480), $w['balanceMin'], 'Mon-Thu target, Good Friday free');
	}

	public function testMonthlyHistoryKeepsTwelveMonthsAndCarriesBalance(): void {
		$net = [];
		for ($d = '2025-01-01'; $d <= '2026-11-16'; $d = Dates::add($d, 1)) {
			if (Dates::dow($d) < 5) {
				$net[$d] = 490; // 10 minutes plus every working day
			}
		}
		$a = Balance::account('2025-01-01', '2026-11-16', 2400, 31, 'BE', $net, []);
		$this->assertCount(12, $a['history']);
		$this->assertSame('2026-11', $a['history'][11]['ym']);
		$this->assertSame('2025-12', $a['history'][0]['ym']);
		$this->assertSame($a['balanceMin'], $a['history'][11]['balanceMin']);
		$this->assertTrue($a['history'][0]['balanceMin'] < $a['history'][11]['balanceMin']);
		$this->assertTrue($a['balanceMin'] > 0);
		$e = Balance::account('2026-11-17', '2026-11-16', 2400, 31, 'BE', [], []);
		$this->assertSame(0, $e['balanceMin']);
		$this->assertCount(0, $e['history']);
	}
}
