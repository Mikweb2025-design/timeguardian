<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Cron;

use OCA\TimeGuardian\Service\WeeklyReportService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;

/** hourly check; the service only acts on Friday afternoon, once per ISO week */
class WeeklyReportJob extends TimedJob {
	public function __construct(ITimeFactory $time, private WeeklyReportService $service) {
		parent::__construct($time);
		$this->setInterval(3600);
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
	}

	protected function run($argument): void {
		$this->service->run();
	}
}
