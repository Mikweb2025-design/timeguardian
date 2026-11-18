<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Integration;

use OCA\TimeGuardian\AppInfo\Application;
use OCA\TimeGuardian\Service\ConfigService;
use OCP\AppFramework\Utility\ITimeFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Optional Deck integration: one card per violation in the configured stack. Uses Deck's database mappers directly
 * (Deck has no public OCP API; its services check permissions against a session user, which does not exist in
 * background jobs). Best effort: any problem is logged and ignored.
 */
class DeckService {
	public function __construct(
		private ContainerInterface $container,
		private ConfigService $config,
		private ITimeFactory $time,
		private LoggerInterface $logger,
	) {
	}

	public function violationCard(string $title, string $description, string $owner): void {
		$stack = $this->config->deckStackId();
		if (!$this->config->integration('deck') || $stack === null) {
			return;
		}
		try {
			$cardClass = 'OCA\\Deck\\Db\\Card';
			$mapperClass = 'OCA\\Deck\\Db\\CardMapper';
			if (!class_exists($cardClass) || !$this->container->has($mapperClass)) {
				return;
			}
			$now = $this->time->getTime();
			$card = new $cardClass();
			$set = [
				'setTitle' => mb_substr($title, 0, 255),
				'setDescription' => $description,
				'setStackId' => $stack,
				'setType' => 'plain',
				'setOrder' => 999,
				'setOwner' => $owner,
				'setCreatedAt' => $now,
				'setLastModified' => $now,
			];
			foreach ($set as $method => $value) {
				if (method_exists($card, $method) || method_exists($card, '__call')) {
					$card->$method($value);
				}
			}
			$this->container->get($mapperClass)->insert($card);
		} catch (\Throwable $e) {
			$this->logger->warning('TimeGuardian Deck card failed: ' . $e->getMessage(), ['app' => Application::APP_ID, 'exception' => $e]);
		}
	}
}
