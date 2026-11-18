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
 * @method ?string getKind()
 * @method void setKind(?string $kind)
 * @method ?string getFromDay()
 * @method void setFromDay(?string $fromDay)
 * @method ?string getToDay()
 * @method void setToDay(?string $toDay)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method ?string getDecidedBy()
 * @method void setDecidedBy(?string $decidedBy)
 * @method ?int getDecidedAt()
 * @method void setDecidedAt(?int $decidedAt)
 * @method ?string getNote()
 * @method void setNote(?string $note)
 * @method ?string getCalendarUid()
 * @method void setCalendarUid(?string $calendarUid)
 * @method ?int getCreatedAt()
 * @method void setCreatedAt(?int $createdAt)
 */
class Absence extends Entity {
	protected ?string $uid = null;
	protected ?string $kind = null;
	protected ?string $fromDay = null;
	protected ?string $toDay = null;
	protected string $status = 'pending';
	protected ?string $decidedBy = null;
	protected ?int $decidedAt = null;
	protected ?string $note = null;
	protected ?string $calendarUid = null;
	protected ?int $createdAt = null;

	public function __construct() {
		$this->addType('uid', 'string');
		$this->addType('kind', 'string');
		$this->addType('fromDay', 'string');
		$this->addType('toDay', 'string');
		$this->addType('status', 'string');
		$this->addType('decidedBy', 'string');
		$this->addType('decidedAt', 'integer');
		$this->addType('note', 'string');
		$this->addType('calendarUid', 'string');
		$this->addType('createdAt', 'integer');
	}
}
