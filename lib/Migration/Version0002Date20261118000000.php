<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Short pairing code: hash column on tg_pair and a small table counting wrong code attempts per IP. */
class Version0002Date20261118000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$changed = false;

		$pair = $schema->getTable('tg_pair');
		if (!$pair->hasColumn('code_hash')) {
			$pair->addColumn('code_hash', Types::STRING, ['notnull' => false, 'length' => 64]);
			$pair->addIndex(['code_hash'], 'tg_pair_code');
			$changed = true;
		}

		if (!$schema->hasTable('tg_pairfail')) {
			$t = $schema->createTable('tg_pairfail');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$t->addColumn('ip_hash', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$t->setPrimaryKey(['id']);
			$t->addIndex(['ip_hash', 'created_at'], 'tg_pairfail_ip');
			$changed = true;
		}

		return $changed ? $schema : null;
	}
}
