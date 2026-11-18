<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Append-only change log. There is deliberately no update(): rows are only ever inserted, and removed in bulk by
 * the retention job or when a user is deleted.
 *
 * @template-extends QBMapper<Audit>
 */
class AuditMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'tg_audit', Audit::class);
	}

	public function update(\OCP\AppFramework\Db\Entity $entity): \OCP\AppFramework\Db\Entity {
		throw new \LogicException('The audit log is append-only');
	}

	/** @return list<Audit> newest first */
	public function findFor(string $uid, int $fromTs, int $toTs, int $limit = 500): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->gte('at_ts', $qb->createNamedParameter($fromTs, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->lt('at_ts', $qb->createNamedParameter($toTs, IQueryBuilder::PARAM_INT)))
			->orderBy('at_ts', 'DESC')->addOrderBy('id', 'DESC')->setMaxResults($limit);
		return array_values($this->findEntities($qb));
	}

	/** @return list<Audit> everything about a user (export) */
	public function findAllOfUser(string $uid): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->orderBy('at_ts', 'ASC');
		return array_values($this->findEntities($qb));
	}

	public function deleteByUid(string $uid): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)));
		return $qb->executeStatement();
	}

	public function deleteOlderThan(int $ts): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())->where($qb->expr()->lt('at_ts', $qb->createNamedParameter($ts, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}
}
