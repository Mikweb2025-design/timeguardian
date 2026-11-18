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
 * @method ?string getTokenHash()
 * @method void setTokenHash(?string $tokenHash)
 * @method ?int getExpiresAt()
 * @method void setExpiresAt(?int $expiresAt)
 * @method int getUsed()
 * @method void setUsed(int $used)
 * @method ?int getCreatedAt()
 * @method void setCreatedAt(?int $createdAt)
 */
class Pair extends Entity {
	protected ?string $uid = null;
	protected ?string $tokenHash = null;
	protected ?int $expiresAt = null;
	protected int $used = 0;
	protected ?int $createdAt = null;

	public function __construct() {
		$this->addType('uid', 'string');
		$this->addType('tokenHash', 'string');
		$this->addType('expiresAt', 'integer');
		$this->addType('used', 'integer');
		$this->addType('createdAt', 'integer');
	}

	public function isUsed(): bool {
		return $this->used === 1;
	}
}
