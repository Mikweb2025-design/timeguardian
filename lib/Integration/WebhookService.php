<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Integration;

use OCA\TimeGuardian\AppInfo\Application;
use OCA\TimeGuardian\Domain\Webhook;
use OCA\TimeGuardian\Service\ConfigService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

/**
 * Optional outgoing webhook, HMAC signed. Events: punch, violation, absence.approved.
 * Headers: X-TG-Event, X-TG-Timestamp, X-TG-Signature (= "sha256=" + HMAC-SHA256(secret, "<timestamp>.<body>")).
 */
class WebhookService {
	public function __construct(
		private IClientService $clients,
		private ConfigService $config,
		private ITimeFactory $time,
		private LoggerInterface $logger,
	) {
	}

	public function emit(string $event, array $data): void {
		$url = $this->config->webhookUrl();
		$secret = $this->config->webhookSecret();
		if ($url === '' || $secret === '' || !in_array($event, Webhook::EVENTS, true)) {
			return;
		}
		try {
			$now = $this->time->getTime();
			$body = Webhook::body($event, $data, $now);
			$this->clients->newClient()->post($url, [
				'body' => $body,
				'headers' => [
					'Content-Type' => 'application/json',
					'X-TG-Event' => $event,
					'X-TG-Timestamp' => (string)$now,
					'X-TG-Signature' => Webhook::signature($secret, $now, $body),
				],
				'timeout' => 5,
				'connect_timeout' => 3,
			]);
		} catch (\Throwable $e) {
			$this->logger->warning('TimeGuardian webhook failed: ' . $e->getMessage(), ['app' => Application::APP_ID]);
		}
	}
}
