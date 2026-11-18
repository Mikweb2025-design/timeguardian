<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Tests\Domain;

use OCA\TimeGuardian\Domain\Messages;
use OCA\TimeGuardian\Domain\Rules;
use OCA\TimeGuardian\Tests\TestCase;

final class RulesTest extends TestCase {
	/** 'HH:MM' with hours up to 47 (night shifts) -> minutes */
	private function min(string $hm): int {
		[$h, $m] = array_map('intval', explode(':', $hm));
		return $h * 60 + $m;
	}

	private function rows(array $vec): array {
		return array_map(fn ($r) => ['uid' => $r['uid'], 'day' => $r['day'], 'startMin' => $this->min($r['start']), 'endMin' => $this->min($r['end']), 'pauseMin' => $r['pause']], $vec);
	}

	public function testSharedVectors(): void {
		$v = $this->vectors('rules');
		foreach ($v['cases'] as $c) {
			$absent = [];
			foreach ($c['absent'] ?? [] as $k) {
				$absent[$k] = true;
			}
			$rules = array_map(fn ($x) => $x['rule'], Rules::evaluate($this->rows($c['rows']), $absent, $c['deriveGaps']));
			sort($rules);
			$this->assertSame($c['expect'], implode(',', $rules), $c['name']);
		}
	}

	public function testTexts(): void {
		foreach ($this->vectors('rules')['texts'] as $t) {
			$this->assertSame($t['text'], Messages::rule($t['rule'], $t['value'], $t['lang']));
		}
	}

	public function testEveryRuleHasATextInEveryLanguage(): void {
		foreach (Messages::LANGS as $l) {
			foreach (Rules::ALL as $r) {
				$t = Messages::rule($r, 90, $l);
				$this->assertStringNotContainsString('{', $t, "$l $r");
				$this->assertTrue($t !== $r, "$l $r has text");
			}
		}
	}

	public function testRuleLevels(): void {
		$rows = $this->rows([['uid' => 'a', 'day' => '2026-11-10', 'start' => '08:00', 'end' => '19:00', 'pause' => 45]]);
		$v = Rules::evaluate($rows, [], false);
		$this->assertSame('verstoss', $v[0]['level']);
		$this->assertSame('DAY10', $v[0]['rule']);
		$this->assertSame(615, $v[0]['value']);
		$h = Rules::evaluate($this->rows([['uid' => 'a', 'day' => '2026-11-10', 'start' => '08:00', 'end' => '17:00', 'pause' => 30]]), [], false);
		$this->assertSame('hinweis', $h[0]['level']);
	}

	public function testNewViolationsOnlyReportsAdditions(): void {
		$one = Rules::evaluate($this->rows([['uid' => 'a', 'day' => '2026-11-10', 'start' => '08:00', 'end' => '16:30', 'pause' => 30]]), [], false);
		$two = Rules::evaluate($this->rows([
			['uid' => 'a', 'day' => '2026-11-10', 'start' => '08:00', 'end' => '16:30', 'pause' => 30],
			['uid' => 'a', 'day' => '2026-11-10', 'start' => '17:00', 'end' => '20:00', 'pause' => 0],
		]), [], false);
		$this->assertCount(0, Rules::newViolations($one, $one));
		$this->assertTrue(count(Rules::newViolations($one, $two)) >= 1);
		$this->assertCount(0, Rules::newViolations($two, $two));
	}

	public function testOrderingNewestDayFirst(): void {
		$rows = $this->rows([
			['uid' => 'a', 'day' => '2026-11-10', 'start' => '08:00', 'end' => '19:00', 'pause' => 45],
			['uid' => 'a', 'day' => '2026-11-12', 'start' => '08:00', 'end' => '19:00', 'pause' => 45],
		]);
		$v = Rules::evaluate($rows, [], false);
		$this->assertSame('2026-11-12', $v[0]['day']);
		$this->assertSame('2026-11-10', $v[1]['day']);
	}

	public function testDayMapDerivesGapsOnlyFromFifteenMinutes(): void {
		$rows = $this->rows([
			['uid' => 'a', 'day' => '2026-11-10', 'start' => '08:00', 'end' => '10:00', 'pause' => 0],
			['uid' => 'a', 'day' => '2026-11-10', 'start' => '10:10', 'end' => '12:00', 'pause' => 0],
			['uid' => 'a', 'day' => '2026-11-10', 'start' => '12:45', 'end' => '15:00', 'pause' => 5],
		]);
		$d = Rules::dayMap($rows, true)['a']['2026-11-10'];
		$this->assertSame(45 + 5, $d['pause'], 'only the 45 min gap counts, plus manual 5');
		$this->assertSame(120 + 110 + 135 - 5, $d['net']);
		$d2 = Rules::dayMap($rows, false)['a']['2026-11-10'];
		$this->assertSame(5, $d2['pause']);
	}

	public function testWeek48AtLastDayOfWeekWithData(): void {
		$rows = [];
		foreach (['2026-11-09', '2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13', '2026-11-14'] as $d) {
			$rows[] = ['uid' => 'a', 'day' => $d, 'startMin' => 480, 'endMin' => 1020, 'pauseMin' => 30];
		}
		$w = array_values(array_filter(Rules::evaluate($rows, [], false), fn ($v) => $v['rule'] === 'WEEK48'));
		$this->assertCount(1, $w);
		$this->assertSame('2026-11-14', $w[0]['day']);
		$this->assertSame(6 * 510, $w[0]['value']);
	}
}
