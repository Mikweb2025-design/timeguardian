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
 * @method ?string getLabel()
 * @method void setLabel(?string $label)
 * @method ?int getCreatedAt()
 * @method void setCreatedAt(?int $createdAt)
 * @method ?int getLastUsed()
 * @method void setLastUsed(?int $lastUsed)
 * @method ?int getExpiresAt()
 * @method void setExpiresAt(?int $expiresAt)
 * @method int getRevoked()
 * @method void setRevoked(int $revoked)
 */
class Device extends Entity {
	protected ?string $uid = null;
	protected ?string $tokenHash = null;
	protected ?string $label = null;
	protected ?int $createdAt = null;
	protected ?int $lastUsed = null;
	protected ?int $expiresAt = null;
	protected int $revoked = 0;

	public function __construct() {
		$this->addType('uid', 'string');
		$this->addType('tokenHash', 'string');
		$this->addType('label', 'string');
		$this->addType('createdAt', 'integer');
		$this->addType('lastUsed', 'integer');
		$this->addType('expiresAt', 'integer');
		$this->addType('revoked', 'integer');
	}

	public function isRevoked(): bool {
		return $this->revoked === 1;
	}
}
