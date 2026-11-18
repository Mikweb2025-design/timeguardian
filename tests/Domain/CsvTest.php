<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Tests\Domain;

use OCA\TimeGuardian\Domain\Csv;
use OCA\TimeGuardian\Tests\TestCase;

final class CsvTest extends TestCase {
	public function testCellVectors(): void {
		foreach ($this->vectors('csv')['cells'] as [$in, $out]) {
			$this->assertSame($out, Csv::cell($in), json_encode($in));
		}
	}

	public function testNumbersAreNotGuarded(): void {
		$this->assertSame('-30', Csv::cell(-30));
		$this->assertSame('0', Csv::cell(0));
		$this->assertSame("'-30", Csv::cell('-30'), 'text that looks like a formula is guarded');
	}

	public function testRoundTrip(): void {
		$csv = Csv::build(['a', 'b', 'c'], [['x, y', "line\nbreak", 'say "hi"'], ['=1+1', 5, '']], true);
		$this->assertTrue(str_starts_with($csv, "\xEF\xBB\xBF"));
		$this->assertStringContainsString("\r\n", $csv);
		$rows = Csv::parse($csv);
		$this->assertCount(2, $rows);
		$this->assertSame('x, y', $rows[0]['a']);
		$this->assertSame('line break', $rows[0]['b']);
		$this->assertSame('say "hi"', $rows[0]['c']);
		$this->assertSame("'=1+1", $rows[1]['a']);
		$this->assertSame('5', $rows[1]['b']);
		$this->assertCount(0, Csv::parse(''));
		$this->assertCount(0, Csv::parse(Csv::build(['a'], [])));
	}
}
