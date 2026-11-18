<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method ?int getAtTs()
 * @method void setAtTs(?int $atTs)
 * @method ?string getActor()
 * @method void setActor(?string $actor)
 * @method ?string getUid()
 * @method void setUid(?string $uid)
 * @method ?string getKind()
 * @method void setKind(?string $kind)
 * @method ?string getEntity()
 * @method void setEntity(?string $entity)
 * @method ?int getEntityId()
 * @method void setEntityId(?int $entityId)
 * @method ?string getBeforeJson()
 * @method void setBeforeJson(?string $beforeJson)
 * @method ?string getAfterJson()
 * @method void setAfterJson(?string $afterJson)
 */
class Audit extends Entity {
	protected ?int $atTs = null;
	protected ?string $actor = null;
	protected ?string $uid = null;
	protected ?string $kind = null;
	protected ?string $entity = null;
	protected ?int $entityId = null;
	protected ?string $beforeJson = null;
	protected ?string $afterJson = null;

	public function __construct() {
		$this->addType('atTs', 'integer');
		$this->addType('actor', 'string');
		$this->addType('uid', 'string');
		$this->addType('kind', 'string');
		$this->addType('entity', 'string');
		$this->addType('entityId', 'integer');
		$this->addType('beforeJson', 'string');
		$this->addType('afterJson', 'string');
	}
}
