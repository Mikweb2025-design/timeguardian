<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Service;

use OCA\TimeGuardian\Db\AbsenceMapper;
use OCA\TimeGuardian\Db\AuditMapper;
use OCA\TimeGuardian\Db\CorrectionMapper;
use OCA\TimeGuardian\Db\DeviceMapper;
use OCA\TimeGuardian\Db\EntryMapper;
use OCA\TimeGuardian\Db\PairMapper;
use OCA\TimeGuardian\Db\StampMapper;
use OCA\TimeGuardian\Db\UserConfMapper;
use OCA\TimeGuardian\Db\ViolationMapper;

/** Removes everything stored about a user (UserDeletedEvent) and applies the retention period. */
class UserDataService {
	public function __construct(
		private EntryMapper $entries,
		private StampMapper $stamps,
		private DeviceMapper $devices,
		private PairMapper $pairs,
		private AbsenceMapper $absences,
		private CorrectionMapper $corrections,
		private AuditMapper $audit,
		private UserConfMapper $conf,
		private ViolationMapper $violations,
	) {
	}

	public function deleteUser(string $uid): void {
		$this->entries->deleteByUid($uid);
		$this->stamps->deleteByUid($uid);
		$this->devices->deleteByUid($uid);
		$this->pairs->deleteByUid($uid);
		$this->absences->deleteByUid($uid);
		$this->corrections->deleteByUid($uid);
		$this->audit->deleteByUid($uid);
		$this->conf->deleteByUid($uid);
		$this->violations->deleteByUid($uid);
	}

	public function revokeDevices(string $uid): void {
		$this->devices->revokeAllOfUser($uid);
	}

	/** deletes data older than $retentionDays (days relative to $today 'YYYY-MM-DD') */
	public function purge(string $today, int $retentionDays, int $nowTs): array {
		$cutDay = \OCA\TimeGuardian\Domain\Dates::add($today, -$retentionDays);
		$cutTs = $nowTs - $retentionDays * 86400;
		return [
			'entries' => $this->entries->deleteOlderThan($cutDay),
			'violations' => $this->violations->deleteOlderThan($cutDay),
			'absences' => $this->absences->deleteOlderThan($cutDay),
			'stamps' => $this->stamps->deleteOlderThan($cutTs),
			'corrections' => $this->corrections->deleteOlderThan($cutTs),
			'audit' => $this->audit->deleteOlderThan($cutTs),
			'devices' => $this->devices->deleteStale($nowTs),
			'pairs' => $this->pairs->deleteExpired($nowTs),
		];
	}
}
