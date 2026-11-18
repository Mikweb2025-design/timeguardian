<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Tests\Domain;

use OCA\TimeGuardian\Domain\Report;
use OCA\TimeGuardian\Domain\Tz;
use OCA\TimeGuardian\Tests\TestCase;

final class ReportTest extends TestCase {
	private function e(int $id, string $day, string $s, string $e, int $p = 0, ?string $uid = 'anna'): array {
		$tz = Tz::zone('Europe/Berlin');
		[$sh, $sm] = array_map('intval', explode(':', $s));
		[$eh, $em] = $e === '' ? [0, 0] : array_map('intval', explode(':', $e));
		return ['id' => $id, 'uid' => $uid, 'day' => $day, 'startTs' => Tz::wall($day, $sh * 60 + $sm, $tz), 'endTs' => $e === '' ? null : Tz::wall($day, $eh * 60 + $em, $tz), 'pauseMin' => $p, 'project' => 'Support', 'note' => null, 'source' => 'scan', 'createdAt' => 1, 'updatedAt' => 1];
	}

	public function testEntryViewShape(): void {
		$tz = Tz::zone('Europe/Berlin');
		$now = Tz::wall('2026-11-17', 12 * 60, $tz);
		$v = Report::entryView($this->e(7, '2026-11-17', '08:02', '16:31', 30), $now, $tz, '2026-11-17', true);
		$this->assertSame(['id', 'uid', 'day', 'start', 'end', 'startHM', 'endHM', 'pauseMin', 'netMin', 'running', 'project', 'note', 'source', 'createdAt', 'updatedAt', 'editable'], array_keys($v));
		$this->assertSame('2026-11-17T07:02:00Z', $v['start']);
		$this->assertSame('08:02', $v['startHM']);
		$this->assertSame('16:31', $v['endHM']);
		$this->assertSame(509 - 30, $v['netMin']);
		$this->assertFalse($v['running']);
		$this->assertTrue($v['editable']);
		$this->assertFalse(Report::entryView($this->e(7, '2026-11-17', '08:02', '16:31'), $now, $tz, '2026-11-17', false)['editable'], 'foreign entries are never editable');
		$this->assertFalse(Report::entryView($this->e(7, '2026-11-09', '08:00', '16:00'), $now, $tz, '2026-11-17', true)['editable'], 'older than 7 days');
		$this->assertTrue(Report::entryView($this->e(7, '2026-11-10', '08:00', '16:00'), $now, $tz, '2026-11-17', true)['editable'], 'exactly 7 days');
	}

	public function testRunningEntryCountsUpToNowOrClip(): void {
		$tz = Tz::zone('Europe/Berlin');
		$now = Tz::wall('2026-11-17', 10 * 60, $tz);
		$v = Report::entryView($this->e(1, '2026-11-17', '08:00', ''), $now, $tz, '2026-11-17', true);
		$this->assertTrue($v['running']);
		$this->assertNull($v['end']);
		$this->assertSame(120, $v['netMin']);
		$frozen = Tz::wall('2026-11-17', 9 * 60, $tz);
		$this->assertSame(60, Report::entryView($this->e(1, '2026-11-17', '08:00', ''), $frozen, $tz, '2026-11-17', true)['netMin'], 'a break freezes the running net');
	}

	public function testWeekDaysWithTargetsHolidaysAbsences(): void {
		$tz = Tz::zone('Europe/Berlin');
		// KW 15/2026: Mon 6.4. is Easter Monday, Tue 7.4. vacation, Wed 8.4. worked 8h
		$entries = [$this->e(1, '2026-04-08', '08:00', '16:30', 30)];
		$now = Tz::wall('2026-04-10', 12 * 60, $tz);
		$days = Report::days('2026-04-06', '2026-04-12', $entries, [], ['2026-04-07' => ['kind' => 'vacation', 'id' => 3]], ['weeklyMin' => 2400, 'daysMask' => 31], 'BE', $tz, 'de', '2026-04-10', $now, true);
		$this->assertCount(7, $days);
		$this->assertSame('eastermonday', $days[0]['holiday']['key']);
		$this->assertSame('Ostermontag', $days[0]['holiday']['name']);
		$this->assertSame(0, $days[0]['sollMin']);
		$this->assertSame(['kind' => 'vacation', 'id' => 3], $days[1]['absence']);
		$this->assertSame(480, $days[1]['creditMin']);
		$this->assertSame(0, $days[1]['deltaMin']);
		$this->assertSame(480, $days[2]['netMin']);
		$this->assertSame(30, $days[2]['pauseMin']);
		$this->assertSame('08:00', $days[2]['firstHM']);
		$this->assertCount(1, $days[2]['entries']);
		$this->assertTrue($days[4]['isToday']);
		$this->assertSame(0, $days[5]['sollMin'], 'Saturday');
		$tot = Report::totals($days);
		$this->assertSame(4 * 480, $tot['sollMin'], 'Tue (vacation, still target), Wed, Thu, Fri; Monday is a holiday');
	}

	public function testWeekHeaderAndProjects(): void {
		$this->assertSame(['from' => '2026-11-16', 'to' => '2026-11-22', 'kw' => 47, 'year' => 2026], Report::weekHeader('2026-11-19'));
		$p = Report::byProject([$this->e(1, '2026-11-16', '08:00', '10:00'), $this->e(2, '2026-11-16', '10:00', '13:00') + [], ['project' => null] + $this->e(3, '2026-11-17', '08:00', '09:00')], 0);
		$this->assertSame('Support', $p[0]['project']);
		$this->assertSame(300, $p[0]['netMin']);
		$this->assertSame(['project' => '', 'netMin' => 60], $p[1]);
	}

	public function testRowsForRulesAcrossMidnightAndOpen(): void {
		$tz = Tz::zone('Europe/Berlin');
		$night = $this->e(1, '2026-11-16', '22:00', '23:00');
		$night['endTs'] = $night['startTs'] + 8 * 3600; // 06:00 next day
		$rows = Report::rows([$night], $tz, 0, false);
		$this->assertSame(1320, $rows[0]['startMin']);
		$this->assertSame(1800, $rows[0]['endMin']);
		$open = $this->e(2, '2026-11-17', '08:00', '');
		$this->assertCount(0, Report::rows([$open], $tz, Tz::wall('2026-11-17', 600, $tz), false));
		$this->assertSame(600, Report::rows([$open], $tz, Tz::wall('2026-11-17', 600, $tz), true)[0]['endMin']);
	}

	public function testNetByDayIgnoresOpenEntries(): void {
		$n = Report::netByDay([$this->e(1, '2026-11-16', '08:00', '12:00', 15), $this->e(2, '2026-11-16', '13:00', '17:00'), $this->e(3, '2026-11-17', '08:00', '')]);
		$this->assertSame(['2026-11-16' => 225 + 240], $n);
	}

	public function testViolationViewIsLocalized(): void {
		$v = Report::violationView(['uid' => 'a', 'day' => '2026-11-10', 'rule' => 'DAY10', 'level' => 'verstoss', 'value' => 615], 'en');
		$this->assertSame('More than 10 hours worked (10.3 h) · § 3 ArbZG', $v['text']);
		$this->assertSame(['uid', 'day', 'rule', 'level', 'value', 'text'], array_keys($v));
	}
}
