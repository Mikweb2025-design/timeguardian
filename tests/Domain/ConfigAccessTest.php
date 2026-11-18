<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Tests\Domain;

use OCA\TimeGuardian\Domain\ConfigAccess;
use OCA\TimeGuardian\Tests\TestCase;

/** Rights of managers on the configuration: everything except the three group names. */
final class ConfigAccessTest extends TestCase {
	public function testGroupsAreAdminOnly(): void {
		$this->assertSame(['chefGroup', 'teamGroup', 'kioskGroup'], ConfigAccess::ADMIN_ONLY);
	}

	public function testManagerMayWriteUncriticalValues(): void {
		$in = ['state' => 'BY', 'timezone' => 'Europe/Berlin', 'defaultWeeklyMin' => 1200, 'retentionDays' => 800, 'selfApproval' => true,
			'integrations' => ['deck' => true], 'webhookUrl' => 'https://example.org/h', 'webhookSecret' => 'x', 'projects' => "A\nB"];
		$this->assertSame([], ConfigAccess::adminOnlyKeys($in));
		$this->assertTrue(ConfigAccess::mayWrite($in, false));
		$this->assertSame($in, ConfigAccess::stripAdminOnly($in));
	}

	public function testManagerMayNotWriteGroups(): void {
		foreach (['chefGroup', 'teamGroup', 'kioskGroup'] as $k) {
			$in = ['state' => 'BE', $k => 'anything'];
			$this->assertSame([$k], ConfigAccess::adminOnlyKeys($in), $k);
			$this->assertFalse(ConfigAccess::mayWrite($in, false), $k);
			$this->assertTrue(ConfigAccess::mayWrite($in, true), 'an admin may');
			$this->assertSame(['state' => 'BE'], ConfigAccess::stripAdminOnly($in));
		}
	}

	public function testEmptyGroupValueIsStillAWriteAttempt(): void {
		// clearing the team group would open the time tracking to every user: it must not slip through
		$this->assertFalse(ConfigAccess::mayWrite(['teamGroup' => ''], false));
		$this->assertFalse(ConfigAccess::mayWrite(['chefGroup' => null], false));
	}

	public function testControllersEnforceTheRights(): void {
		$root = dirname(__DIR__, 2) . '/lib/Controller/';
		$cfg = (string)file_get_contents($root . 'ConfigController.php');
		$this->assertSame(2, substr_count($cfg, 'requireChef()'), 'show and update need a manager');
		$this->assertTrue(str_contains($cfg, 'ConfigAccess::mayWrite('));
		$this->assertTrue(str_contains($cfg, "new TgException('forbidden'"));
		$this->assertSame(2, substr_count($cfg, '#[NoAdminRequired]'), 'managers are not necessarily admins');
		$admin = (string)file_get_contents($root . 'AdminController.php');
		$this->assertTrue(preg_match('/^\\s*#\\[NoAdminRequired\\]/m', $admin) !== 1, 'the admin endpoint stays admin only');
		$setup = (string)file_get_contents($root . 'SetupController.php');
		$this->assertTrue(str_contains($setup, 'requireMember()'));
	}

	public function testSetupChecklistIsOnlyForManagers(): void {
		$src = (string)file_get_contents(dirname(__DIR__, 2) . '/lib/Service/SetupService.php');
		$this->assertTrue(str_contains($src, "'checklist' => \$isChef ? \$this->checklist() : null"));
		$this->assertTrue(str_contains($src, 'if ($isChef) {'), 'counts and member names only for managers');
	}
}
