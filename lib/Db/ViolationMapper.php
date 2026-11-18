<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<Violation> materialized rule hits */
class ViolationMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'tg_violation', Violation::class);
	}

	/** @return list<Violation> newest day first */
	public function findRange(string $uid, string $from, string $to): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->gte('day', $qb->createNamedParameter($from)))
			->andWhere($qb->expr()->lte('day', $qb->createNamedParameter($to)))
			->orderBy('day', 'DESC')->addOrderBy('rule', 'ASC');
		return array_values($this->findEntities($qb));
	}

	/**
	 * @param list<string> $uids
	 * @return list<Violation>
	 */
	public function findRangeForUsers(array $uids, string $from, string $to): array {
		if ($uids === []) {
			return [];
		}
		$out = [];
		foreach (array_chunk($uids, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')->from($this->getTableName())
				->where($qb->expr()->in('uid', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_STR_ARRAY)))
				->andWhere($qb->expr()->gte('day', $qb->createNamedParameter($from)))
				->andWhere($qb->expr()->lte('day', $qb->createNamedParameter($to)))
				->orderBy('day', 'DESC');
			array_push($out, ...$this->findEntities($qb));
		}
		return $out;
	}

	public function deleteRange(string $uid, string $from, string $to): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->gte('day', $qb->createNamedParameter($from)))
			->andWhere($qb->expr()->lte('day', $qb->createNamedParameter($to)));
		return $qb->executeStatement();
	}

	public function deleteById(int $id): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}

	public function deleteByUid(string $uid): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)));
		return $qb->executeStatement();
	}

	public function deleteOlderThan(string $day): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())->where($qb->expr()->lt('day', $qb->createNamedParameter($day)));
		return $qb->executeStatement();
	}
}
