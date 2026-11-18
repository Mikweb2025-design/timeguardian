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

/** @template-extends QBMapper<UserConf> */
class UserConfMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'tg_user_conf', UserConf::class);
	}

	public function findByUid(string $uid): ?UserConf {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * @param list<string> $uids
	 * @return array<string,UserConf>
	 */
	public function findByUids(array $uids): array {
		if ($uids === []) {
			return [];
		}
		$out = [];
		foreach (array_chunk($uids, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')->from($this->getTableName())
				->where($qb->expr()->in('uid', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_STR_ARRAY)));
			foreach ($this->findEntities($qb) as $c) {
				$out[$c->getUid()] = $c;
			}
		}
		return $out;
	}

	public function deleteByUid(string $uid): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)));
		return $qb->executeStatement();
	}
}
