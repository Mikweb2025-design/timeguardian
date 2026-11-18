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
 * @method int getWeeklyMin()
 * @method void setWeeklyMin(int $weeklyMin)
 * @method int getDaysMask()
 * @method void setDaysMask(int $daysMask)
 * @method string getMode()
 * @method void setMode(string $mode)
 * @method ?string getLang()
 * @method void setLang(?string $lang)
 * @method ?string getStateCache()
 * @method void setStateCache(?string $stateCache)
 */
class UserConf extends Entity {
	protected ?string $uid = null;
	protected int $weeklyMin = 2400;
	protected int $daysMask = 31;
	protected string $mode = 'simple';
	protected ?string $lang = null;
	protected ?string $stateCache = null;

	public function __construct() {
		$this->addType('uid', 'string');
		$this->addType('weeklyMin', 'integer');
		$this->addType('daysMask', 'integer');
		$this->addType('mode', 'string');
		$this->addType('lang', 'string');
		$this->addType('stateCache', 'string');
	}
}
