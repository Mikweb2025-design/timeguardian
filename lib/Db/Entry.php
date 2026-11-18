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
 * @method ?int getStartTs()
 * @method void setStartTs(?int $startTs)
 * @method ?int getEndTs()
 * @method void setEndTs(?int $endTs)
 * @method int getPauseMin()
 * @method void setPauseMin(int $pauseMin)
 * @method ?string getProject()
 * @method void setProject(?string $project)
 * @method ?string getNote()
 * @method void setNote(?string $note)
 * @method string getSource()
 * @method void setSource(string $source)
 * @method ?int getCreatedAt()
 * @method void setCreatedAt(?int $createdAt)
 * @method ?int getUpdatedAt()
 * @method void setUpdatedAt(?int $updatedAt)
 */
class Entry extends Entity {
	protected ?string $uid = null;
	protected ?string $day = null;
	protected ?int $startTs = null;
	protected ?int $endTs = null;
	protected int $pauseMin = 0;
	protected ?string $project = null;
	protected ?string $note = null;
	protected string $source = 'scan';
	protected ?int $createdAt = null;
	protected ?int $updatedAt = null;

	public function __construct() {
		$this->addType('uid', 'string');
		$this->addType('day', 'string');
		$this->addType('startTs', 'integer');
		$this->addType('endTs', 'integer');
		$this->addType('pauseMin', 'integer');
		$this->addType('project', 'string');
		$this->addType('note', 'string');
		$this->addType('source', 'string');
		$this->addType('createdAt', 'integer');
		$this->addType('updatedAt', 'integer');
	}

	/** the shape used by OCA\TimeGuardian\Domain\Report */
	public function toRow(): array {
		return [
			'id' => (int)$this->getId(),
			'uid' => (string)$this->uid,
			'day' => (string)$this->day,
			'startTs' => (int)$this->startTs,
			'endTs' => $this->endTs,
			'pauseMin' => $this->pauseMin,
			'project' => $this->project,
			'note' => $this->note,
			'source' => $this->source,
			'createdAt' => (int)$this->createdAt,
			'updatedAt' => (int)$this->updatedAt,
		];
	}
}
