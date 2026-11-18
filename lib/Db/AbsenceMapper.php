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

/** @template-extends QBMapper<Absence> */
class AbsenceMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'tg_absence', Absence::class);
	}

	/** @throws DoesNotExistException */
	public function find(int $id): Absence {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/**
	 * Absences overlapping [from, to].
	 * @param list<string> $uids
	 * @return list<Absence>
	 */
	public function findOverlapping(array $uids, string $from, string $to, ?string $status = null): array {
		if ($uids === []) {
			return [];
		}
		$out = [];
		foreach (array_chunk($uids, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')->from($this->getTableName())
				->where($qb->expr()->in('uid', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_STR_ARRAY)))
				->andWhere($qb->expr()->lte('from_day', $qb->createNamedParameter($to)))
				->andWhere($qb->expr()->gte('to_day', $qb->createNamedParameter($from)))
				->orderBy('from_day', 'ASC');
			if ($status !== null) {
				$qb->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($status)));
			}
			array_push($out, ...$this->findEntities($qb));
		}
		return $out;
	}

	/** @return list<Absence> */
	public function findPending(array $uids): array {
		if ($uids === []) {
			return [];
		}
		$out = [];
		foreach (array_chunk($uids, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')->from($this->getTableName())
				->where($qb->expr()->in('uid', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_STR_ARRAY)))
				->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('pending')))
				->orderBy('from_day', 'ASC');
			array_push($out, ...$this->findEntities($qb));
		}
		return $out;
	}

	/** @return list<Absence> */
	public function findAllOfUser(string $uid): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->orderBy('from_day', 'DESC');
		return array_values($this->findEntities($qb));
	}

	/**
	 * Atomic decision: only a pending request can be decided (two managers clicking at once).
	 */
	public function decide(int $id, string $status, string $by, int $now): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('status', $qb->createNamedParameter($status))
			->set('decided_by', $qb->createNamedParameter($by))
			->set('decided_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('pending')));
		return $qb->executeStatement() === 1;
	}

	public function deleteByUid(string $uid): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)));
		return $qb->executeStatement();
	}

	public function deleteOlderThan(string $day): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())->where($qb->expr()->lt('to_day', $qb->createNamedParameter($day)));
		return $qb->executeStatement();
	}
}
