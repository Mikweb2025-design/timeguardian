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

/**
 * Initial schema. Timestamps are stored as unix seconds (UTC) in BIGINT columns, days as 'YYYY-MM-DD' strings in the
 * configured time zone. Booleans are SMALLINT 0/1.
 */
class Version0001Date20261117000000 extends SimpleMigrationStep {
	/**
	 * @param Closure():ISchemaWrapper $schemaClosure
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$id = static function ($t): void {
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$t->setPrimaryKey(['id']);
		};

		if (!$schema->hasTable('tg_entry')) {
			$t = $schema->createTable('tg_entry');
			$t->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$t->addColumn('uid', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('day', Types::STRING, ['notnull' => true, 'length' => 10]);
			$t->addColumn('start_ts', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$t->addColumn('end_ts', Types::BIGINT, ['notnull' => false, 'length' => 20]);
			$t->addColumn('pause_min', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$t->addColumn('project', Types::STRING, ['notnull' => false, 'length' => 64]);
			$t->addColumn('note', Types::STRING, ['notnull' => false, 'length' => 255]);
			$t->addColumn('source', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'scan']);
			$t->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$t->addColumn('updated_at', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$t->setPrimaryKey(['id']);
			$t->addIndex(['uid', 'day'], 'tg_entry_uid_day');
			$t->addIndex(['day'], 'tg_entry_day');
		}

		if (!$schema->hasTable('tg_stamp')) {
			$t = $schema->createTable('tg_stamp');
			$id($t);
			$t->addColumn('uid', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('at_ts', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$t->addColumn('action', Types::STRING, ['notnull' => true, 'length' => 8]);
			$t->addColumn('device_id', Types::BIGINT, ['notnull' => false, 'length' => 20]);
			$t->addColumn('source', Types::STRING, ['notnull' => true, 'length' => 8, 'default' => 'scan']);
			$t->addColumn('ip_hash', Types::STRING, ['notnull' => false, 'length' => 64]);
			$t->addIndex(['uid', 'at_ts'], 'tg_stamp_uid_at');
			$t->addIndex(['device_id', 'at_ts'], 'tg_stamp_dev_at');
		}

		if (!$schema->hasTable('tg_device')) {
			$t = $schema->createTable('tg_device');
			$id($t);
			$t->addColumn('uid', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('token_hash', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('label', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$t->addColumn('last_used', Types::BIGINT, ['notnull' => true, 'length' => 20, 'default' => 0]);
			$t->addColumn('expires_at', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$t->addColumn('revoked', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
			$t->addUniqueIndex(['token_hash'], 'tg_device_hash');
			$t->addIndex(['uid'], 'tg_device_uid');
		}

		if (!$schema->hasTable('tg_pair')) {
			$t = $schema->createTable('tg_pair');
			$id($t);
			$t->addColumn('uid', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('token_hash', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('expires_at', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$t->addColumn('used', Types::SMALLINT, ['notnull' => true, 'default' => 0]);
			$t->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$t->addUniqueIndex(['token_hash'], 'tg_pair_hash');
		}

		if (!$schema->hasTable('tg_absence')) {
			$t = $schema->createTable('tg_absence');
			$id($t);
			$t->addColumn('uid', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('kind', Types::STRING, ['notnull' => true, 'length' => 16]);
			$t->addColumn('from_day', Types::STRING, ['notnull' => true, 'length' => 10]);
			$t->addColumn('to_day', Types::STRING, ['notnull' => true, 'length' => 10]);
			$t->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'pending']);
			$t->addColumn('decided_by', Types::STRING, ['notnull' => false, 'length' => 64]);
			$t->addColumn('decided_at', Types::BIGINT, ['notnull' => false, 'length' => 20]);
			$t->addColumn('note', Types::STRING, ['notnull' => false, 'length' => 255]);
			$t->addColumn('calendar_uid', Types::STRING, ['notnull' => false, 'length' => 255]);
			$t->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$t->addIndex(['uid', 'from_day'], 'tg_abs_uid_from');
			$t->addIndex(['status'], 'tg_abs_status');
		}

		if (!$schema->hasTable('tg_correction')) {
			$t = $schema->createTable('tg_correction');
			$id($t);
			$t->addColumn('uid', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('entry_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$t->addColumn('proposed_start', Types::BIGINT, ['notnull' => false, 'length' => 20]);
			$t->addColumn('proposed_end', Types::BIGINT, ['notnull' => false, 'length' => 20]);
			$t->addColumn('proposed_pause', Types::INTEGER, ['notnull' => false]);
			$t->addColumn('reason', Types::STRING, ['notnull' => true, 'length' => 500]);
			$t->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 16, 'default' => 'pending']);
			$t->addColumn('decided_by', Types::STRING, ['notnull' => false, 'length' => 64]);
			$t->addColumn('decided_at', Types::BIGINT, ['notnull' => false, 'length' => 20]);
			$t->addColumn('decision_note', Types::STRING, ['notnull' => false, 'length' => 255]);
			$t->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$t->addIndex(['uid'], 'tg_corr_uid');
			$t->addIndex(['status'], 'tg_corr_status');
			$t->addIndex(['entry_id'], 'tg_corr_entry');
		}

		if (!$schema->hasTable('tg_audit')) {
			$t = $schema->createTable('tg_audit');
			$id($t);
			$t->addColumn('at_ts', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$t->addColumn('actor', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('uid', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('kind', Types::STRING, ['notnull' => true, 'length' => 32]);
			$t->addColumn('entity', Types::STRING, ['notnull' => true, 'length' => 16]);
			$t->addColumn('entity_id', Types::BIGINT, ['notnull' => false, 'length' => 20]);
			$t->addColumn('before_json', Types::TEXT, ['notnull' => false]);
			$t->addColumn('after_json', Types::TEXT, ['notnull' => false]);
			$t->addIndex(['uid', 'at_ts'], 'tg_audit_uid_at');
		}

		if (!$schema->hasTable('tg_user_conf')) {
			$t = $schema->createTable('tg_user_conf');
			$id($t);
			$t->addColumn('uid', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('weekly_min', Types::INTEGER, ['notnull' => true, 'default' => 2400]);
			$t->addColumn('days_mask', Types::INTEGER, ['notnull' => true, 'default' => 31]);
			$t->addColumn('mode', Types::STRING, ['notnull' => true, 'length' => 8, 'default' => 'simple']);
			$t->addColumn('lang', Types::STRING, ['notnull' => false, 'length' => 8]);
			$t->addColumn('state_cache', Types::TEXT, ['notnull' => false]);
			$t->addUniqueIndex(['uid'], 'tg_uconf_uid');
		}

		if (!$schema->hasTable('tg_violation')) {
			$t = $schema->createTable('tg_violation');
			$id($t);
			$t->addColumn('uid', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('day', Types::STRING, ['notnull' => true, 'length' => 10]);
			$t->addColumn('rule', Types::STRING, ['notnull' => true, 'length' => 16]);
			$t->addColumn('level', Types::STRING, ['notnull' => true, 'length' => 16]);
			$t->addColumn('text_key', Types::STRING, ['notnull' => true, 'length' => 32]);
			$t->addColumn('params', Types::TEXT, ['notnull' => false]);
			$t->addColumn('created_at', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$t->addUniqueIndex(['uid', 'day', 'rule'], 'tg_viol_uid_day_rule');
			$t->addIndex(['day'], 'tg_viol_day');
		}

		return $schema;
	}
}
