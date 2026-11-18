<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Cron;

use OCA\TimeGuardian\Domain\Tz;
use OCA\TimeGuardian\Service\ConfigService;
use OCA\TimeGuardian\Service\UserDataService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;

/** daily: removes data older than the configured retention period (default 2 years, ArbZG § 16 (2)) */
class RetentionJob extends TimedJob {
	public function __construct(ITimeFactory $time, private UserDataService $data, private ConfigService $config) {
		parent::__construct($time);
		$this->setInterval(86400);
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
	}

	protected function run($argument): void {
		$now = $this->time->getTime();
		$this->data->purge(Tz::day($now, $this->config->tz()), $this->config->retentionDays(), $now);
	}
}
