<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Domain;

/**
 * Who may see or decide what. Pure decisions on top of a group membership callback.
 * - a user sees their own data
 * - a manager (member of the manager group) sees team members, nobody else
 * - Nextcloud admins are NOT managers automatically
 */
final class AccessPolicy {
	/** @var \Closure(string,string):bool */
	private \Closure $inGroup;

	/** @param callable(string,string):bool $isInGroup fn(uid, groupId) */
	public function __construct(
		private string $chefGroup,
		private string $teamGroup,
		callable $isInGroup,
		private string $kioskGroup = '',
		private bool $selfApproval = false,
	) {
		$this->inGroup = \Closure::fromCallable($isInGroup);
	}

	public function isChef(string $uid): bool {
		return $this->chefGroup !== '' && ($this->inGroup)($uid, $this->chefGroup);
	}

	/** may use the time tracking: team group member (empty team group = everybody) or manager */
	public function isTeamMember(string $uid): bool {
		return $this->teamGroup === '' || ($this->inGroup)($uid, $this->teamGroup) || $this->isChef($uid);
	}

	public function canView(string $actor, string $target): bool {
		if ($actor === '' || $target === '') {
			return false;
		}
		return $actor === $target || ($this->isChef($actor) && $this->isTeamMember($target));
	}

	public function canDecide(string $actor, string $target): bool {
		if (!$this->isChef($actor) || !$this->isTeamMember($target)) {
			return false;
		}
		return $actor !== $target || $this->selfApproval;
	}

	public function canKiosk(string $uid, bool $isAdmin): bool {
		return $isAdmin || $this->isChef($uid) || ($this->kioskGroup !== '' && ($this->inGroup)($uid, $this->kioskGroup));
	}

	/** may change the weekly target of $target (managers only, for team members) */
	public function canSetTarget(string $actor, string $target): bool {
		return $this->isChef($actor) && $this->isTeamMember($target);
	}
}
