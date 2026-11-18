<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<Stamp> append-only audit of punches */
class StampMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'tg_stamp', Stamp::class);
	}

	public function lastOf(string $uid): ?Stamp {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->orderBy('at_ts', 'DESC')->addOrderBy('id', 'DESC')->setMaxResults(1);
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/** punches made with a device since $ts (rate limit) */
	public function countForDeviceSince(int $deviceId, int $ts): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*'))->from($this->getTableName())
			->where($qb->expr()->eq('device_id', $qb->createNamedParameter($deviceId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->gte('at_ts', $qb->createNamedParameter($ts, IQueryBuilder::PARAM_INT)));
		$res = $qb->executeQuery();
		$n = (int)$res->fetchOne();
		$res->closeCursor();
		return $n;
	}

	/** all punches ever recorded (setup checklist: "first punch exists") */
	public function countAll(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*'))->from($this->getTableName());
		$res = $qb->executeQuery();
		$n = (int)$res->fetchOne();
		$res->closeCursor();
		return $n;
	}

	/** @return list<Stamp> */
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
