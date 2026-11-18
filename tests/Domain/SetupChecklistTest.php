<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Tests\Domain;

use OCA\TimeGuardian\Domain\SetupChecklist;
use OCA\TimeGuardian\Tests\TestCase;

final class SetupChecklistTest extends TestCase {
	private function facts(array $o = []): array {
		return $o + ['chefs' => 0, 'members' => 0, 'stateSet' => false, 'targetSet' => false, 'kioskSeenAt' => null, 'devices' => 0, 'punches' => 0];
	}

	/** @return array<string,?bool> */
	private function done(array $c): array {
		$out = [];
		foreach ($c['steps'] as $s) {
			$out[$s['id']] = $s['done'];
		}
		return $out;
	}

	public function testFreshInstallHasNothingDone(): void {
		$c = SetupChecklist::build($this->facts());
		$this->assertSame(['roles', 'region', 'kiosk', 'phone', 'install', 'punch'], array_column($c['steps'], 'id'));
		$this->assertSame(['roles' => false, 'region' => false, 'kiosk' => false, 'phone' => false, 'install' => null, 'punch' => false], $this->done($c));
		$this->assertSame(5, $c['required']);
		$this->assertSame(0, $c['doneCount']);
		$this->assertFalse($c['complete']);
	}

	public function testRolesNeedAChefAndAParticipant(): void {
		$this->assertFalse($this->done(SetupChecklist::build($this->facts(['chefs' => 1])))['roles']);
		$this->assertFalse($this->done(SetupChecklist::build($this->facts(['members' => 3])))['roles']);
		$c = SetupChecklist::build($this->facts(['chefs' => 1, 'members' => 3]));
		$this->assertTrue($this->done($c)['roles']);
		$this->assertSame(['chefs' => 1, 'members' => 3], $c['steps'][0]['params']);
	}

	public function testRegionNeedsStateAndTargetChosen(): void {
		$this->assertFalse($this->done(SetupChecklist::build($this->facts(['stateSet' => true])))['region']);
		$this->assertFalse($this->done(SetupChecklist::build($this->facts(['targetSet' => true])))['region']);
		$this->assertTrue($this->done(SetupChecklist::build($this->facts(['stateSet' => true, 'targetSet' => true])))['region']);
	}

	public function testKioskPhoneAndPunch(): void {
		$c = SetupChecklist::build($this->facts(['kioskSeenAt' => 1700000000, 'devices' => 2, 'punches' => 1]));
		$d = $this->done($c);
		$this->assertTrue($d['kiosk']);
		$this->assertTrue($d['phone']);
		$this->assertTrue($d['punch']);
		$this->assertSame(1700000000, $c['steps'][2]['params']['seenAt']);
		$this->assertSame(3, $c['doneCount']);
	}

	public function testInstallIsAHintAndNeverBlocksCompletion(): void {
		$c = SetupChecklist::build($this->facts(['chefs' => 1, 'members' => 2, 'stateSet' => true, 'targetSet' => true, 'kioskSeenAt' => 5, 'devices' => 1, 'punches' => 9]));
		$this->assertTrue($c['complete']);
		$this->assertSame(5, $c['doneCount']);
		$install = $c['steps'][4];
		$this->assertSame('hint', $install['kind']);
		$this->assertNull($install['done'], 'only the browser can tell');
	}
}
