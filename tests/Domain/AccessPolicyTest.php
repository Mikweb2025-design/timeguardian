<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Tests\Domain;

use OCA\TimeGuardian\Domain\AccessPolicy;
use OCA\TimeGuardian\Tests\TestCase;

final class AccessPolicyTest extends TestCase {
	/** @var array<string,list<string>> group => members */
	private array $groups = [
		'tg-chef' => ['boss'],
		'tg-team' => ['anna', 'ben', 'boss2'],
		'kiosk' => ['reception'],
	];

	private function policy(string $team = 'tg-team', bool $self = false): AccessPolicy {
		return new AccessPolicy('tg-chef', $team, fn (string $u, string $g) => in_array($u, $this->groups[$g] ?? [], true), 'kiosk', $self);
	}

	public function testUserNeverSeesAnotherUser(): void {
		$p = $this->policy();
		$this->assertTrue($p->canView('anna', 'anna'));
		$this->assertFalse($p->canView('anna', 'ben'));
		$this->assertFalse($p->canView('ben', 'anna'));
		$this->assertFalse($p->canView('anna', 'boss'), 'nor the manager');
		$this->assertFalse($p->canView('anna', ''));
		$this->assertFalse($p->canView('', 'anna'));
		$this->assertFalse($p->canView('', ''));
	}

	public function testManagerSeesOnlyTeamMembers(): void {
		$p = $this->policy();
		$this->assertTrue($p->canView('boss', 'anna'));
		$this->assertTrue($p->canView('boss', 'boss'));
		$this->assertFalse($p->canView('boss', 'outsider'), 'not in the team group');
		$this->assertFalse($p->canView('boss', 'reception'));
	}

	public function testEmptyTeamGroupMeansEveryone(): void {
		$p = $this->policy('');
		$this->assertTrue($p->isTeamMember('anyone'));
		$this->assertTrue($p->canView('boss', 'anyone'));
		$this->assertFalse($p->canView('anna', 'ben'), 'still no peeking between employees');
	}

	public function testNextcloudAdminIsNotAManager(): void {
		$p = $this->policy();
		$this->assertFalse($p->isChef('admin'));
		$this->assertFalse($p->canView('admin', 'anna'));
		$this->assertFalse($p->canDecide('admin', 'anna'));
		$this->assertTrue($p->canKiosk('admin', true), 'admins may open the kiosk page (it shows no personal data)');
		$this->assertFalse($p->canKiosk('admin', false));
	}

	public function testDecisionRights(): void {
		$p = $this->policy();
		$this->assertTrue($p->canDecide('boss', 'anna'));
		$this->assertFalse($p->canDecide('anna', 'ben'));
		$this->assertFalse($p->canDecide('boss', 'boss'), 'no self approval by default');
		$this->assertTrue($this->policy('tg-team', true)->canDecide('boss', 'boss'), 'unless the admin allows it');
		$this->assertFalse($p->canDecide('boss', 'outsider'));
	}

	public function testKioskAndTargetRights(): void {
		$p = $this->policy();
		$this->assertTrue($p->canKiosk('boss', false));
		$this->assertTrue($p->canKiosk('reception', false));
		$this->assertFalse($p->canKiosk('anna', false));
		$this->assertTrue($p->canSetTarget('boss', 'anna'));
		$this->assertFalse($p->canSetTarget('anna', 'anna'), 'only managers set the target');
		$this->assertFalse($p->canSetTarget('anna', 'ben'));
	}

	public function testNoChefGroupConfiguredMeansNoChefs(): void {
		$p = new AccessPolicy('', 'tg-team', fn () => true);
		$this->assertFalse($p->isChef('anna'));
		$this->assertFalse($p->canView('anna', 'ben'));
	}
}
