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
 * @method ?int getEntryId()
 * @method void setEntryId(?int $entryId)
 * @method ?int getProposedStart()
 * @method void setProposedStart(?int $proposedStart)
 * @method ?int getProposedEnd()
 * @method void setProposedEnd(?int $proposedEnd)
 * @method ?int getProposedPause()
 * @method void setProposedPause(?int $proposedPause)
 * @method ?string getReason()
 * @method void setReason(?string $reason)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method ?string getDecidedBy()
 * @method void setDecidedBy(?string $decidedBy)
 * @method ?int getDecidedAt()
 * @method void setDecidedAt(?int $decidedAt)
 * @method ?string getDecisionNote()
 * @method void setDecisionNote(?string $decisionNote)
 * @method ?int getCreatedAt()
 * @method void setCreatedAt(?int $createdAt)
 */
class Correction extends Entity {
	protected ?string $uid = null;
	protected ?int $entryId = null;
	protected ?int $proposedStart = null;
	protected ?int $proposedEnd = null;
	protected ?int $proposedPause = null;
	protected ?string $reason = null;
	protected string $status = 'pending';
	protected ?string $decidedBy = null;
	protected ?int $decidedAt = null;
	protected ?string $decisionNote = null;
	protected ?int $createdAt = null;

	public function __construct() {
		$this->addType('uid', 'string');
		$this->addType('entryId', 'integer');
		$this->addType('proposedStart', 'integer');
		$this->addType('proposedEnd', 'integer');
		$this->addType('proposedPause', 'integer');
		$this->addType('reason', 'string');
		$this->addType('status', 'string');
		$this->addType('decidedBy', 'string');
		$this->addType('decidedAt', 'integer');
		$this->addType('decisionNote', 'string');
		$this->addType('createdAt', 'integer');
	}
}
