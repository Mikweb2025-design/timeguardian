<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Integration;

use OCA\TimeGuardian\AppInfo\Application;
use OCA\TimeGuardian\Service\ConfigService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IURLGenerator;
use OCP\Notification\IManager;
use Psr\Log\LoggerInterface;

/**
 * Nextcloud notifications. Parameters are stored raw (minutes, rule ids, ISO days) and rendered into the reader's
 * language by the Notifier. Never throws: a failing notification must not break a booking.
 */
class NotificationService {
	public function __construct(
		private IManager $manager,
		private ConfigService $config,
		private ITimeFactory $time,
		private IURLGenerator $urls,
		private LoggerInterface $logger,
	) {
	}

	/** @param array<string,string|int> $params */
	public function send(string $uid, string $subject, array $params, string $objectType, string $objectId, string $route = 'timeguardian.page.index', string $hash = ''): void {
		if (!$this->config->integration('notifications')) {
			return;
		}
		try {
			$this->dismiss($uid, $objectType, $objectId);
			$n = $this->manager->createNotification();
			$n->setApp(Application::APP_ID)
				->setUser($uid)
				->setDateTime((new \DateTime())->setTimestamp($this->time->getTime()))
				->setObject($objectType, $objectId)
				->setSubject($subject, array_map('strval', $params))
				->setLink($this->urls->getAbsoluteURL($this->urls->linkToRoute($route)) . $hash);
			$this->manager->notify($n);
		} catch (\Throwable $e) {
			$this->logger->warning('TimeGuardian notification failed: ' . $e->getMessage(), ['app' => Application::APP_ID, 'exception' => $e]);
		}
	}

	/** removes earlier notifications of the same object (no duplicates, and decided requests disappear) */
	public function dismiss(string $uid, string $objectType, string $objectId): void {
		try {
			$n = $this->manager->createNotification();
			$n->setApp(Application::APP_ID)->setUser($uid)->setObject($objectType, $objectId);
			$this->manager->markProcessed($n);
		} catch (\Throwable $e) {
			$this->logger->debug('TimeGuardian notification dismiss failed: ' . $e->getMessage(), ['app' => Application::APP_ID]);
		}
	}

	/** removes the notification of an object for several users */
	public function dismissForAll(array $uids, string $objectType, string $objectId): void {
		foreach ($uids as $uid) {
			$this->dismiss((string)$uid, $objectType, $objectId);
		}
	}
}
