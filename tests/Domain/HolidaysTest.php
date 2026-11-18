<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Tests\Domain;

use OCA\TimeGuardian\Domain\Holidays;
use OCA\TimeGuardian\Domain\Messages;
use OCA\TimeGuardian\Tests\TestCase;

final class HolidaysTest extends TestCase {
	public function testEasterSunday2024To2030(): void {
		foreach ($this->vectors('holidays')['easter'] as [$y, $d]) {
			$this->assertSame($d, Holidays::easter($y), (string)$y);
		}
	}

	public function testStateSpecificDays(): void {
		$v = $this->vectors('holidays');
		foreach ($v['days'] as $c) {
			$y = (int)substr($c['day'], 0, 4);
			$h = Holidays::forYear($y, $c['state']);
			$this->assertSame($c['key'], $h[$c['day']] ?? null, $c['state'] . ' ' . $c['day']);
		}
		foreach ($v['notDays'] as $c) {
			$y = (int)substr($c['day'], 0, 4);
			$this->assertFalse(isset(Holidays::forYear($y, $c['state'])[$c['day']]), $c['state'] . ' ' . $c['day'] . ' is no holiday');
		}
	}

	public function testHolidayCountsPerState(): void {
		foreach ($this->vectors('holidays')['counts'] as $c) {
			$this->assertSame($c['n'], count(Holidays::forYear($c['year'], $c['state'])), $c['state']);
		}
	}

	public function testEveryStateHasAtLeastNineNationalDays(): void {
		foreach (Holidays::STATES as $s) {
			$this->assertTrue(count(Holidays::forYear(2027, $s)) >= 9, $s);
		}
		$this->assertSame(Holidays::forYear(2026, 'BE'), Holidays::forYear(2026, 'xx'), 'unknown state falls back to BE');
	}

	public function testRangeSpansYears(): void {
		$r = Holidays::inRange('2026-12-20', '2027-01-10', 'BE');
		$this->assertSame(['2026-12-25' => 'christmas1', '2026-12-26' => 'christmas2', '2027-01-01' => 'newyear'], $r);
	}

	public function testNamesInEveryLanguage(): void {
		$keys = [];
		foreach (Holidays::STATES as $s) {
			foreach (Holidays::forYear(2026, $s) as $k) {
				$keys[$k] = true;
			}
		}
		foreach (Messages::LANGS as $l) {
			foreach (array_keys($keys) as $k) {
				$this->assertTrue(Messages::holiday($k, $l) !== $k, "$l $k");
			}
		}
	}
}
