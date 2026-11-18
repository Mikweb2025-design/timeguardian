<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method ?string getUid()
 * @method void setUid(?string $uid)
 * @method ?string getDay()
 * @method void setDay(?string $day)
 * @method ?string getRule()
 * @method void setRule(?string $rule)
 * @method ?string getLevel()
 * @method void setLevel(?string $level)
 * @method ?string getTextKey()
 * @method void setTextKey(?string $textKey)
 * @method ?string getParams()
 * @method void setParams(?string $params)
 * @method ?int getCreatedAt()
 * @method void setCreatedAt(?int $createdAt)
 */
class Violation extends Entity {
	protected ?string $uid = null;
	protected ?string $day = null;
	protected ?string $rule = null;
	protected ?string $level = null;
	protected ?string $textKey = null;
	protected ?string $params = null;
	protected ?int $createdAt = null;

	public function __construct() {
		$this->addType('uid', 'string');
		$this->addType('day', 'string');
		$this->addType('rule', 'string');
		$this->addType('level', 'string');
		$this->addType('textKey', 'string');
		$this->addType('params', 'string');
		$this->addType('createdAt', 'integer');
	}

	public function toRow(): array {
		$p = json_decode((string)$this->params, true);
		return [
			'uid' => (string)$this->uid,
			'day' => (string)$this->day,
			'rule' => (string)$this->rule,
			'level' => (string)$this->level,
			'value' => is_array($p) ? (int)($p['value'] ?? 0) : 0,
			'key' => $this->uid . '|' . $this->day . '|' . $this->rule,
		];
	}
}
