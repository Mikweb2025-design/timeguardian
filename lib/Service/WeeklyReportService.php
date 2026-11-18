<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Service;

use OCA\TimeGuardian\Domain\Dates;
use OCA\TimeGuardian\Domain\Tz;
use OCP\AppFramework\Utility\ITimeFactory;

/** Friday afternoon: every manager gets the week's violations of the team as a notification (once per ISO week). */
class WeeklyReportService {
	public function __construct(
		private ReportService $reports,
		private AccessService $access,
		private ConfigService $config,
		private EffectsService $effects,
		private ITimeFactory $time,
	) {
	}

	/** @return int number of managers notified */
	public function run(): int {
		$now = $this->time->getTime();
		$today = Tz::day($now, $this->config->tz());
		if (Dates::dow($today) !== 4 || (int)(new \DateTimeImmutable('@' . $now))->setTimezone($this->config->tz())->format('G') < 15) {
			return 0;
		}
		$w = Dates::isoWeek($today);
		$mark = $w['year'] . '-W' . $w['week'];
		if ($this->config->lastWeekly() === $mark) {
			return 0;
		}
		$chefs = $this->access->chefUids();
		if ($chefs === []) {
			return 0;
		}
		$ov = $this->reports->teamOverview($chefs[0], $today);
		$verstoss = 0;
		$hinweis = 0;
		$net = 0;
		foreach ($ov['people'] as $p) {
			$verstoss += $p['violations']['verstoss'];
			$hinweis += $p['violations']['hinweis'];
			$net += $p['netMin'];
		}
		foreach ($chefs as $c) {
			$this->effects->weekly($c, ['kw' => $w['week'], 'year' => $w['year'], 'verstoss' => $verstoss, 'hinweis' => $hinweis, 'min' => $net]);
		}
		$this->config->setLastWeekly($mark);
		return count($chefs);
	}
}
