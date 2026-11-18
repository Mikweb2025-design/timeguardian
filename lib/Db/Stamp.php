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
 * @method ?int getAtTs()
 * @method void setAtTs(?int $atTs)
 * @method ?string getAction()
 * @method void setAction(?string $action)
 * @method ?int getDeviceId()
 * @method void setDeviceId(?int $deviceId)
 * @method string getSource()
 * @method void setSource(string $source)
 * @method ?string getIpHash()
 * @method void setIpHash(?string $ipHash)
 */
class Stamp extends Entity {
	protected ?string $uid = null;
	protected ?int $atTs = null;
	protected ?string $action = null;
	protected ?int $deviceId = null;
	protected string $source = 'scan';
	protected ?string $ipHash = null;

	public function __construct() {
		$this->addType('uid', 'string');
		$this->addType('atTs', 'integer');
		$this->addType('action', 'string');
		$this->addType('deviceId', 'integer');
		$this->addType('source', 'string');
		$this->addType('ipHash', 'string');
	}
}
