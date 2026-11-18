<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** Wrong pairing code attempts per (hashed) IP address; the basis of the strict rate limit of the code path. */
class PairFailMapper {
	private const TABLE = 'tg_pairfail';

	public function __construct(private IDBConnection $db) {
	}

	public function countSince(string $ipHash, int $since): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'n'))->from(self::TABLE)
			->where($qb->expr()->eq('ip_hash', $qb->createNamedParameter($ipHash)))
			->andWhere($qb->expr()->gte('created_at', $qb->createNamedParameter($since, IQueryBuilder::PARAM_INT)));
		$res = $qb->executeQuery();
		$n = (int)$res->fetchOne();
		$res->closeCursor();
		return $n;
	}

	public function add(string $ipHash, int $now): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert(self::TABLE)->values([
			'ip_hash' => $qb->createNamedParameter($ipHash),
			'created_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT),
		]);
		$qb->executeStatement();
	}

	public function purge(int $before): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete(self::TABLE)->where($qb->expr()->lt('created_at', $qb->createNamedParameter($before, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
