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

/** @template-extends QBMapper<Entry> */
class EntryMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'tg_entry', Entry::class);
	}

	/** @throws DoesNotExistException */
	public function find(int $id): Entry {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/** @return list<Entry> */
	public function findRange(string $uid, string $from, string $to): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->gte('day', $qb->createNamedParameter($from)))
			->andWhere($qb->expr()->lte('day', $qb->createNamedParameter($to)))
			->orderBy('start_ts', 'ASC')->addOrderBy('id', 'ASC');
		return array_values($this->findEntities($qb));
	}

	/**
	 * @param list<string> $uids
	 * @return list<Entry>
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
				->orderBy('start_ts', 'ASC');
			array_push($out, ...$this->findEntities($qb));
		}
		return $out;
	}

	/** @return list<Entry> every entry of the user (export, deletion) */
	public function findAllOfUser(string $uid): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->orderBy('start_ts', 'ASC');
		return array_values($this->findEntities($qb));
	}

	public function findOpen(string $uid): ?Entry {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->isNull('end_ts'))
			->orderBy('start_ts', 'DESC')->setMaxResults(1);
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/** @return list<Entry> all open entries (cron) */
	public function findAllOpen(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())->where($qb->expr()->isNull('end_ts'));
		return array_values($this->findEntities($qb));
	}

	/** an entry of the user that overlaps [start, end) (open entries count as running until now) */
	public function findOverlap(string $uid, int $startTs, int $endTs, ?int $excludeId = null): ?Entry {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->lt('start_ts', $qb->createNamedParameter($endTs, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->isNull('end_ts'),
				$qb->expr()->gt('end_ts', $qb->createNamedParameter($startTs, IQueryBuilder::PARAM_INT)),
			))
			->setMaxResults(1);
		if ($excludeId !== null) {
			$qb->andWhere($qb->expr()->neq('id', $qb->createNamedParameter($excludeId, IQueryBuilder::PARAM_INT)));
		}
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function firstDay(string $uid): ?string {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->min('day'))->from($this->getTableName())
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)));
		$res = $qb->executeQuery();
		$v = $res->fetchOne();
		$res->closeCursor();
		return is_string($v) && $v !== '' ? $v : null;
	}

	/** @return list<string> distinct users with entries on or after $fromDay */
	public function usersWithEntriesSince(string $fromDay): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('uid')->from($this->getTableName())
			->where($qb->expr()->gte('day', $qb->createNamedParameter($fromDay)));
		$res = $qb->executeQuery();
		$out = [];
		while (($v = $res->fetchOne()) !== false) {
			$out[] = (string)$v;
		}
		$res->closeCursor();
		return $out;
	}

	public function deleteByUid(string $uid): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)));
		return $qb->executeStatement();
	}

	public function deleteOlderThan(string $day): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->lt('day', $qb->createNamedParameter($day)))
			->andWhere($qb->expr()->isNotNull('end_ts'));
		return $qb->executeStatement();
	}
}
