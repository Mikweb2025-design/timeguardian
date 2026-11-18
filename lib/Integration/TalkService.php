<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Integration;

use OCA\TimeGuardian\AppInfo\Application;
use OCA\TimeGuardian\Service\ConfigService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Optional Talk integration: a message into the managers' room (the admin sets the room token).
 * Talk has no stable public OCP API for posting into a room, so this talks to Talk's chat manager and is strictly
 * best effort. Notifications are the reliable channel.
 */
class TalkService {
	public function __construct(
		private ContainerInterface $container,
		private ConfigService $config,
		private LoggerInterface $logger,
	) {
	}

	public function post(string $message): void {
		$token = $this->config->talkRoomToken();
		if (!$this->config->integration('talk') || $token === '') {
			return;
		}
		try {
			$managerClass = 'OCA\\Talk\\Manager';
			$chatClass = 'OCA\\Talk\\Chat\\ChatManager';
			if (!$this->container->has($managerClass) || !$this->container->has($chatClass)) {
				return;
			}
			$room = $this->container->get($managerClass)->getRoomByToken($token);
			$this->container->get($chatClass)->sendMessage($room, null, 'bots', 'bot-timeguardian', mb_substr($message, 0, 1000), new \DateTime());
		} catch (\Throwable $e) {
			$this->logger->warning('TimeGuardian Talk message failed: ' . $e->getMessage(), ['app' => Application::APP_ID, 'exception' => $e]);
		}
	}
}
