<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Service;

use OCA\TimeGuardian\AppInfo\Application;
use OCA\TimeGuardian\Db\EntryMapper;
use OCA\TimeGuardian\Domain\Dates;
use OCA\TimeGuardian\Domain\LiveWarnings;
use OCA\TimeGuardian\Domain\Report;
use OCA\TimeGuardian\Domain\Rules;
use OCP\AppFramework\Utility\ITimeFactory;
use Psr\Log\LoggerInterface;

/** Warns people who are working right now: 10 h limit near, break due, forgotten clock-out. */
class LiveWarningService {
	public function __construct(
		private EntryMapper $entries,
		private StatusService $status,
		private UserConfService $userConf,
		private ConfigService $config,
		private EffectsService $effects,
		private ITimeFactory $time,
		private LoggerInterface $logger,
	) {
	}

	/** @return int number of warnings sent */
	public function run(): int {
		if (!$this->config->liveWarnings()) {
			return 0;
		}
		$now = $this->time->getTime();
		$tz = $this->config->tz();
		$sent = 0;
		$seen = [];
		foreach ($this->entries->findAllOpen() as $open) {
			$uid = $open->getUid();
			if (isset($seen[$uid])) {
				continue;
			}
			$seen[$uid] = true;
			try {
				$state = $this->status->state($uid);
				if ($state['state'] !== 'working' || $state['since'] === null) {
					continue;
				}
				$day = $open->getDay();
				$rows = Report::rows(array_map(fn ($e) => $e->toRow(), $this->entries->findRange($uid, $day, $day)), $tz, $now, true);
				$agg = Rules::dayMap($rows, true)[$uid][$day] ?? null;
				$net = $agg['net'] ?? 0;
				$pause = $agg['pause'] ?? 0;
				$kinds = LiveWarnings::evaluate('working', (int)Dates::parseIso($state['since']), $now, $net, $pause);
				$cache = $this->userConf->stateCache($uid);
				$warn = $cache['warn'] ?? [];
				if (($warn['day'] ?? '') !== $day) {
					$warn = ['day' => $day, 'sent' => []];
				}
				$todo = LiveWarnings::pending($kinds, $warn['sent']);
				foreach ($todo as $kind) {
					$this->effects->live($uid, $kind, $kind === LiveWarnings::FORGOT ? intdiv($now - (int)Dates::parseIso($state['since']), 60) : $net, $day);
					$warn['sent'][] = $kind;
					$sent++;
				}
				if ($todo !== []) {
					$cache['warn'] = $warn;
					$this->userConf->saveStateCache($uid, $cache);
				}
			} catch (\Throwable $e) {
				$this->logger->warning('TimeGuardian live warning failed for one user: ' . $e->getMessage(), ['app' => Application::APP_ID]);
			}
		}
		return $sent;
	}
}
