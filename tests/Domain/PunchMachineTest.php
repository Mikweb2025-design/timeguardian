<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Tests\Domain;

use OCA\TimeGuardian\Domain\PunchMachine as P;
use OCA\TimeGuardian\Domain\Tz;
use OCA\TimeGuardian\Tests\TestCase;

final class PunchMachineTest extends TestCase {
	public function testReferenceDayVectors(): void {
		foreach ($this->vectors('punch')['days'] as $c) {
			$rows = array_map(fn ($r) => ['time' => $r[0], 'kind' => $r[1], 'note' => $r[2]], $c['rows']);
			$d = P::day($rows, $c['now']);
			foreach ($c['expect'] as $k => $v) {
				$this->assertSame($v, $d[$k], json_encode($c['rows']) . ' ' . $k);
			}
		}
		$d = P::day([['time' => '08:00', 'kind' => 'start', 'note' => 'früh'], ['time' => '12:00', 'kind' => 'pause'], ['time' => '12:45', 'kind' => 'resume'], ['time' => '17:00', 'kind' => 'end', 'note' => 'fertig']], 1200);
		$this->assertSame(['früh', 'fertig'], $d['notes']);
	}

	public function testAllowedMatrix(): void {
		foreach ($this->vectors('punch')['allowed'] as [$state, $action, $ok]) {
			$this->assertSame($ok, P::allowed($state, $action), "$state $action");
		}
	}

	public function testNextState(): void {
		$this->assertSame('working', P::next('none', 'in'));
		$this->assertSame('pause', P::next('working', 'pause'));
		$this->assertSame('working', P::next('pause', 'resume'));
		$this->assertSame('done', P::next('working', 'out'));
		$this->assertSame('working', P::next('done', 'in'));
		$this->assertTgError('invalid_transition', fn () => P::next('none', 'out'));
		$this->assertSame('done', P::next('pause', 'out'));
		$this->assertTgError('invalid_transition', fn () => P::next('pause', 'in'));
	}

	public function testOutFromShortPauseBooksThePause(): void {
		// 08:00 start, break from 12:00, clock out 12:30 -> entry ends now, 30 min break booked on top of 5 earlier
		$start = 1790000000 - (1790000000 % 60);
		$pauseAt = $start + 4 * 3600;
		$now = $pauseAt + 30 * 60 + 20;
		$o = P::outFromPause($start, $pauseAt, $now, 5);
		$this->assertSame(intdiv($now, 60) * 60, $o['endTs']);
		$this->assertSame(35, $o['pauseMin']);
	}

	public function testOutFromForgottenPauseEndsWhereTheBreakBegan(): void {
		// break since 06:14 (here: start + 5 h 18 min of work), clocked out after 5 h 47 min of "break"
		$start = 1790000000 - (1790000000 % 60);
		$pauseAt = $start + (5 * 60 + 18) * 60 + 13;
		$now = $pauseAt + (5 * 60 + 47) * 60;
		$o = P::outFromPause($start, $pauseAt, $now, 0);
		$this->assertSame(intdiv($pauseAt, 60) * 60, $o['endTs']);
		$this->assertSame(0, $o['pauseMin'], 'a forgotten break is not booked');
		$this->assertSame(318, intdiv($o['endTs'] - $start, 60) - $o['pauseMin'], 'net stays 5 h 18 min');
		// exactly at the limit counts as a normal break
		$edge = P::outFromPause($start, $pauseAt, $pauseAt + P::LONG_PAUSE_MIN * 60, 0);
		$this->assertSame(P::LONG_PAUSE_MIN, $edge['pauseMin']);
		// never before the minimum entry length
		$this->assertSame($start + 60, P::outFromPause($start, $start, $start + 7 * 3600, 0)['endTs']);
	}

	public function testDecide(): void {
		foreach ($this->vectors('punch')['decide'] as [$state, $mode, $action, $expect]) {
			$this->assertSame($expect, P::decide($state, $mode, $action), "$state $mode " . json_encode($action));
		}
		$this->assertTgError('invalid_transition', fn () => P::decide('none', 'simple', 'out'));
		$this->assertTgError('invalid_transition', fn () => P::decide('working', 'simple', 'bogus'));
		$this->assertTgError('invalid_transition', fn () => P::decide('pause', 'pause', 'in'));
	}

	public function testFullDayInBothModes(): void {
		$s = 'none';
		foreach (['in', 'out', 'in', 'out'] as $expected) {
			$a = P::decide($s, 'simple', null);
			$this->assertSame($expected, $a);
			$s = P::next($s, $a);
		}
		$s = 'none';
		foreach (['in', 'pause', 'resume', 'pause', 'resume'] as $expected) {
			$a = P::decide($s, 'pause', null);
			$this->assertSame($expected, $a);
			$s = P::next($s, $a);
		}
		$this->assertSame('done', P::next($s, P::decide($s, 'pause', 'out')));
	}

	public function testNeedsCode(): void {
		$this->assertTrue(P::needsCode('in'));
		$this->assertTrue(P::needsCode(null));
		$this->assertTrue(P::needsCode(''));
		$this->assertFalse(P::needsCode('out'));
		$this->assertFalse(P::needsCode('pause'));
		$this->assertFalse(P::needsCode('resume'));
	}

	public function testMinimumDistance(): void {
		P::checkInterval(null, 1000);
		P::checkInterval(1000, 1000 + 120);
		$this->assertTgError('too_fast', fn () => P::checkInterval(1000, 1000 + 119));
		$this->assertTgError('too_fast', fn () => P::checkInterval(1000, 1000));
	}

	public function testResolveStateFromLastStamp(): void {
		$tz = Tz::zone('Europe/Berlin');
		$now = gmmktime(10, 0, 0, 11, 17, 2026);
		$this->assertSame('none', P::resolveState(null, $now, $tz));
		$this->assertSame('working', P::resolveState(['action' => 'in', 'at' => $now - 600], $now, $tz));
		$this->assertSame('working', P::resolveState(['action' => 'resume', 'at' => $now - 600], $now, $tz));
		$this->assertSame('pause', P::resolveState(['action' => 'pause', 'at' => $now - 600], $now, $tz));
		$this->assertSame('done', P::resolveState(['action' => 'out', 'at' => $now - 600], $now, $tz));
		$this->assertSame('none', P::resolveState(['action' => 'out', 'at' => $now - 86400], $now, $tz), 'a finished day of yesterday starts fresh');
		$this->assertSame('working', P::resolveState(['action' => 'in', 'at' => $now - 86400], $now, $tz), 'forgotten clock-out stays open');
	}
}
