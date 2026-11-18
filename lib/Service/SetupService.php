<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Service;

use OCA\TimeGuardian\Db\DeviceMapper;
use OCA\TimeGuardian\Db\StampMapper;
use OCA\TimeGuardian\Domain\SetupChecklist;
use OCP\AppFramework\Utility\ITimeFactory;

/** Who is who, and how far the setup is. Feeds the start page, the configuration page and the admin page. */
class SetupService {
	public function __construct(
		private AccessService $access,
		private ConfigService $config,
		private DeviceMapper $devices,
		private StampMapper $stamps,
		private ITimeFactory $time,
	) {
	}

	/** @return array{chefs:int,members:int,stateSet:bool,targetSet:bool,kioskSeenAt:?int,devices:int,punches:int} */
	public function facts(): array {
		return [
			'chefs' => count($this->access->chefUids()),
			'members' => count($this->access->memberUids()),
			'stateSet' => $this->config->hasKey('state'),
			'targetSet' => $this->config->hasKey('default_weekly_min'),
			'kioskSeenAt' => $this->config->kioskSeenAt(),
			'devices' => $this->devices->countAllActive($this->time->getTime()),
			'punches' => $this->stamps->countAll(),
		];
	}

	public function checklist(): array {
		return SetupChecklist::build($this->facts());
	}

	/**
	 * Roles as seen by the caller. Everybody learns who the managers are; only managers get the counts.
	 * @return array<string,mixed>
	 */
	public function roles(string $uid): array {
		$isChef = $this->access->isChef($uid);
		$r = [
			'isChef' => $isChef,
			'isAdmin' => $this->access->isAdmin($uid),
			'chefs' => $this->access->chefNames(),
			'chefGroup' => $this->config->chefGroup(),
			'teamGroup' => $this->config->teamGroup(),
			'kioskGroup' => $this->config->kioskGroup(),
		];
		if ($isChef) {
			$members = $this->access->memberUids();
			$r['memberCount'] = count($members);
			$r['members'] = array_map(fn (string $u) => $this->access->displayName($u), array_slice($members, 0, 100));
			$r['chefGroupExists'] = $this->access->groupExists($this->config->chefGroup());
			$r['teamGroupExists'] = $this->config->teamGroup() === '' || $this->access->groupExists($this->config->teamGroup());
		}
		return $r;
	}

	/** GET /api/setup */
	public function forUser(string $uid): array {
		$isChef = $this->access->isChef($uid);
		return [
			'roles' => $this->roles($uid),
			'checklist' => $isChef ? $this->checklist() : null,
		];
	}
}
