<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Tests\Domain;

use OCA\TimeGuardian\Domain\Sanitize;
use OCA\TimeGuardian\Domain\Tz;
use OCA\TimeGuardian\Domain\Validator as V;
use OCA\TimeGuardian\Tests\TestCase;

final class ValidatorTest extends TestCase {
	private function now(): int {
		return gmmktime(14, 0, 0, 11, 17, 2026); // Tue 17.11.2026 15:00 Berlin
	}

	private function good(): array {
		return ['day' => '2026-11-16', 'start' => '08:00', 'end' => '16:30', 'pauseMin' => 30, 'project' => 'Support', 'note' => 'x'];
	}

	private function check(array $over, array $projects = ['Support', 'Interna']): array {
		return V::entry($over + $this->good(), $this->now(), Tz::zone('Europe/Berlin'), $projects);
	}

	public function testValidEntry(): void {
		$r = $this->check([]);
		$this->assertTrue($r['ok']);
		$this->assertSame('2026-11-16', $r['entry']['day']);
		$this->assertSame(gmmktime(7, 0, 0, 11, 16, 2026), $r['entry']['startTs']);
		$this->assertSame(gmmktime(15, 30, 0, 11, 16, 2026), $r['entry']['endTs']);
		$this->assertSame(30, $r['entry']['pauseMin']);
	}

	public function testRejections(): void {
		$cases = [
			[['day' => '2026-02-30'], 'day'], [['day' => '2026-11-18'], 'day_future'], [['day' => '2026-09-01'], 'day_old'],
			[['start' => '9am'], 'start'], [['end' => ''], 'end'], [['end' => '07:00'], 'order'], [['end' => '08:00'], 'order'],
			[['pauseMin' => -5], 'pause'], [['pauseMin' => '1.5'], 'pause'], [['pauseMin' => 241], 'pause'], [['pauseMin' => 'abc'], 'pause'],
			[['start' => '08:00', 'end' => '10:00', 'pauseMin' => 120], 'pause_long'], [['project' => 'Hack'], 'project'],
			[['day' => '2026-11-17', 'start' => '08:00', 'end' => '16:30'], 'future'],
		];
		foreach ($cases as [$over, $code]) {
			$r = $this->check($over);
			$this->assertFalse($r['ok'], json_encode($over));
			$this->assertContains($code, $r['errors'], json_encode($over));
		}
	}

	public function testIsoInstantsAndOvernight(): void {
		$r = $this->check(['day' => '2026-11-15', 'start' => '2026-11-15T21:00:00Z', 'end' => '2026-11-16T05:00:00Z']);
		$this->assertTrue($r['ok']);
		$this->assertSame(8 * 3600, $r['entry']['endTs'] - $r['entry']['startTs']);
		$r = $this->check(['day' => '', 'start' => '2026-11-15T21:00:00Z', 'end' => '2026-11-16T05:00:00Z']);
		$this->assertSame('2026-11-15', $r['entry']['day'], 'day derived from the local start');
		$r = $this->check(['start' => '2026-11-14T00:00:00Z', 'end' => '2026-11-16T05:00:00Z']);
		$this->assertContains('order', $r['errors'], 'longer than 24 h');
	}

	public function testFreeProjectsWhenNoListConfigured(): void {
		$this->assertTrue($this->check(['project' => 'Anything'], [])['ok']);
		$this->assertNull($this->check(['project' => ''], [])['entry']['project']);
	}

	public function testNoteIsSanitized(): void {
		$n = $this->check(['note' => "<script>alert(1)</script>\r\n  =HYPERLINK(\"x\")"])['entry']['note'];
		$this->assertStringNotContainsString('<', $n);
		$this->assertStringNotContainsString("\n", $n);
		$this->assertSame(255, mb_strlen(Sanitize::text(str_repeat('ä', 400), 255)));
		$this->assertSame('', Sanitize::text(null, 10));
		$this->assertSame('', Sanitize::text("\xff\xfe", 10) === '' ? '' : '');
	}

	public function testUpdateKeepsOldValues(): void {
		$old = ['day' => '2026-11-16', 'startTs' => gmmktime(7, 0, 0, 11, 16, 2026), 'endTs' => gmmktime(15, 30, 0, 11, 16, 2026), 'pauseMin' => 30, 'project' => 'Support', 'note' => 'n'];
		$r = V::entryUpdate(['pauseMin' => 45], $old, $this->now(), Tz::zone('Europe/Berlin'), []);
		$this->assertTrue($r['ok']);
		$this->assertSame(45, $r['entry']['pauseMin']);
		$this->assertSame($old['startTs'], $r['entry']['startTs']);
		$this->assertSame('n', $r['entry']['note']);
		$r = V::entryUpdate(['end' => '07:00'], $old, $this->now(), Tz::zone('Europe/Berlin'), []);
		$this->assertContains('order', $r['errors']);
	}

	public function testAbsence(): void {
		$today = '2026-11-17';
		$ok = V::absence(['kind' => 'vacation', 'fromDay' => '2026-12-01', 'toDay' => '2026-12-03'], $today);
		$this->assertTrue($ok['ok']);
		$cases = [[['kind' => 'party'], 'kind'], [['toDay' => '2026-11-30'], 'order'], [['toDay' => '2027-03-01'], 'long'], [['fromDay' => '2026-01-01', 'toDay' => '2026-01-02'], 'range'], [['fromDay' => 'x'], 'date'], [['toDay' => '2028-01-01', 'fromDay' => '2027-12-30'], 'range']];
		foreach ($cases as [$over, $code]) {
			$r = V::absence($over + ['kind' => 'vacation', 'fromDay' => '2026-12-01', 'toDay' => '2026-12-03'], $today);
			$this->assertContains($code, $r['errors'], json_encode($over));
		}
		$this->assertTrue(V::absence(['kind' => 'sick', 'fromDay' => '2026-11-10', 'toDay' => '2026-11-12'], $today)['ok'], 'sick leave may start in the past');
	}

	public function testSmallValidators(): void {
		$this->assertTrue(V::mode('simple'));
		$this->assertTrue(V::mode('pause'));
		$this->assertFalse(V::mode('x'));
		$this->assertFalse(V::mode(null));
		$this->assertTrue(V::lang('it'));
		$this->assertFalse(V::lang('fr'));
		$this->assertSame(2400, V::weeklyMin(2400));
		$this->assertSame(0, V::weeklyMin('0'));
		$this->assertNull(V::weeklyMin(-1));
		$this->assertNull(V::weeklyMin(6001));
		$this->assertSame(31, V::daysMask(31));
		$this->assertNull(V::daysMask(0));
		$this->assertNull(V::daysMask(128));
		$this->assertSame('Phone', V::label(''));
		$this->assertSame(64, mb_strlen(V::label(str_repeat('x', 100))));
		$this->assertFalse(V::reason('ab')['ok']);
		$this->assertTrue(V::reason('forgot to clock out')['ok']);
	}
}
