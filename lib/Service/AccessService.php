<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Service;

use OCA\TimeGuardian\Domain\AccessPolicy;
use OCA\TimeGuardian\Domain\TgException;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;

/**
 * The one place for every permission check. Nextcloud admins are NOT managers: managers come only from the
 * configured manager group.
 */
class AccessService {
	/** @var array<string,string> */
	private array $names = [];

	public function __construct(
		private ConfigService $config,
		private IGroupManager $groups,
		private IUserManager $users,
		private IUserSession $session,
	) {
	}

	public function policy(): AccessPolicy {
		return new AccessPolicy(
			$this->config->chefGroup(),
			$this->config->teamGroup(),
			fn (string $uid, string $gid): bool => $this->groups->isInGroup($uid, $gid),
			$this->config->kioskGroup(),
			$this->config->selfApproval(),
		);
	}

	/** uid of the logged in user or null */
	public function sessionUid(): ?string {
		$u = $this->session->getUser();
		return $u === null ? null : $u->getUID();
	}

	/** @throws TgException unauthorized */
	public function requireSessionUid(): string {
		$uid = $this->sessionUid();
		if ($uid === null) {
			throw new TgException('unauthorized');
		}
		return $uid;
	}

	/** logged in and allowed to use the time tracking at all */
	public function requireMember(): string {
		$uid = $this->requireSessionUid();
		$this->assertMember($uid);
		return $uid;
	}

	public function assertMember(string $uid): void {
		if (!$this->policy()->isTeamMember($uid)) {
			throw new TgException('not_in_team');
		}
	}

	public function requireChef(): string {
		$uid = $this->requireSessionUid();
		if (!$this->policy()->isChef($uid)) {
			throw new TgException('forbidden');
		}
		return $uid;
	}

	public function isChef(string $uid): bool {
		return $this->policy()->isChef($uid);
	}

	public function isAdmin(string $uid): bool {
		return $this->groups->isAdmin($uid);
	}

	public function canKiosk(string $uid): bool {
		return $this->policy()->canKiosk($uid, $this->isAdmin($uid));
	}

	/** the caller may see the data of $target: themself or (as manager) a team member. Unknown and forbidden look the same. */
	public function assertCanView(string $actor, string $target): void {
		if (!$this->policy()->canView($actor, $target)) {
			throw new TgException('not_found');
		}
	}

	public function assertCanDecide(string $actor, string $target): void {
		if (!$this->policy()->canDecide($actor, $target)) {
			throw new TgException('forbidden');
		}
	}

	public function displayName(string $uid): string {
		if (!isset($this->names[$uid])) {
			$n = $this->users->getDisplayName($uid);
			$this->names[$uid] = ($n === null || $n === '') ? $uid : $n;
		}
		return $this->names[$uid];
	}

	public function userExists(string $uid): bool {
		return $this->users->userExists($uid);
	}

	/**
	 * Everyone who takes part in the time tracking (team group, or all enabled users when no team group is set),
	 * plus managers.
	 * @return list<string> sorted by display name
	 */
	public function memberUids(): array {
		$uids = [];
		$add = function (string $gid) use (&$uids): void {
			$g = $this->groups->get($gid);
			if ($g === null) {
				return;
			}
			foreach ($g->getUsers() as $u) {
				if ($u->isEnabled()) {
					$uids[$u->getUID()] = true;
				}
			}
		};
		$team = $this->config->teamGroup();
		if ($team === '') {
			foreach ($this->users->search('', 1000) as $u) {
				if ($u->isEnabled()) {
					$uids[$u->getUID()] = true;
				}
			}
		} else {
			$add($team);
		}
		if ($this->config->chefGroup() !== '') {
			$add($this->config->chefGroup());
		}
		$list = array_map('strval', array_keys($uids));
		usort($list, fn (string $a, string $b) => strcasecmp($this->displayName($a), $this->displayName($b)));
		return $list;
	}

	public function groupExists(string $gid): bool {
		return $gid !== '' && $this->groups->groupExists($gid);
	}

	/** @return list<string> display names of all managers */
	public function chefNames(): array {
		$out = array_map(fn (string $u) => $this->displayName($u), $this->chefUids());
		sort($out, SORT_NATURAL | SORT_FLAG_CASE);
		return $out;
	}

	/** @return list<string> uids of all managers */
	public function chefUids(): array {
		$gid = $this->config->chefGroup();
		if ($gid === '') {
			return [];
		}
		$g = $this->groups->get($gid);
		if ($g === null) {
			return [];
		}
		$out = [];
		foreach ($g->getUsers() as $u) {
			if ($u->isEnabled()) {
				$out[] = $u->getUID();
			}
		}
		return $out;
	}
}
