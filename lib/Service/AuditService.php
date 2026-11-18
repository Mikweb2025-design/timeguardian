<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Service;

use OCA\TimeGuardian\Db\Audit;
use OCA\TimeGuardian\Db\AuditMapper;
use OCA\TimeGuardian\Domain\Dates;
use OCP\AppFramework\Utility\ITimeFactory;

/** Append-only change log: who changed what, when, old -> new. */
class AuditService {
	public function __construct(
		private AuditMapper $mapper,
		private ITimeFactory $time,
	) {
	}

	public function log(string $actor, string $uid, string $kind, string $entity, ?int $entityId, ?array $before, ?array $after): void {
		$a = new Audit();
		$a->setAtTs($this->time->getTime());
		$a->setActor($actor);
		$a->setUid($uid);
		$a->setKind($kind);
		$a->setEntity($entity);
		$a->setEntityId($entityId);
		$a->setBeforeJson($before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE));
		$a->setAfterJson($after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE));
		$this->mapper->insert($a);
	}

	/** @return list<array> */
	public function list(string $uid, string $fromDay, string $toDay): array {
		$from = (int)Dates::parseDay($fromDay);
		$to = (int)Dates::parseDay($toDay) + 86400;
		return array_map([$this, 'view'], $this->mapper->findFor($uid, $from, $to));
	}

	public function view(Audit $a): array {
		$b = json_decode((string)$a->getBeforeJson(), true);
		$f = json_decode((string)$a->getAfterJson(), true);
		return [
			'id' => (int)$a->getId(),
			'at' => Dates::iso((int)$a->getAtTs()),
			'actor' => $a->getActor(),
			'uid' => $a->getUid(),
			'kind' => $a->getKind(),
			'entity' => $a->getEntity(),
			'entityId' => $a->getEntityId(),
			'before' => is_array($b) ? $b : null,
			'after' => is_array($f) ? $f : null,
		];
	}
}
