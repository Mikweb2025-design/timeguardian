<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Cron;

use OCA\TimeGuardian\Service\LiveWarningService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;

/** every 5 minutes: live warnings for people who are working */
class LiveWarningsJob extends TimedJob {
	public function __construct(ITimeFactory $time, private LiveWarningService $service) {
		parent::__construct($time);
		$this->setInterval(300);
		$this->setTimeSensitivity(IJob::TIME_SENSITIVE);
	}

	protected function run($argument): void {
		$this->service->run();
	}
}
