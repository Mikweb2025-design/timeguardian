<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Controller;

use OCA\TimeGuardian\Service\AccessService;
use OCA\TimeGuardian\Service\DeviceService;
use OCA\TimeGuardian\Service\UserConfService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class DeviceController extends BaseController {
	public function __construct(
		IRequest $request,
		AccessService $access,
		UserConfService $userConf,
		LoggerInterface $logger,
		private DeviceService $devices,
	) {
		parent::__construct($request, $access, $userConf, $logger);
	}

	#[NoAdminRequired]
	#[UserRateLimit(limit: 20, period: 300)]
	public function pair(): Response {
		return $this->run(fn () => $this->devices->createPairing($this->access->requireMember()), Http::STATUS_CREATED);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[AnonRateLimit(limit: 20, period: 300)]
	#[UserRateLimit(limit: 20, period: 300)]
	public function redeem(): Response {
		return $this->run(function () {
			$p = $this->body();
			return $this->devices->redeem(
				is_string($p['token'] ?? null) ? $p['token'] : '',
				is_string($p['label'] ?? null) ? $p['label'] : '',
				is_string($p['code'] ?? null) ? $p['code'] : '',
				$this->request->getRemoteAddress(),
			);
		});
	}

	#[NoAdminRequired]
	public function index(): Response {
		return $this->run(fn () => ['devices' => $this->devices->list($this->access->requireSessionUid())]);
	}

	#[NoAdminRequired]
	public function revoke(int $id): Response {
		return $this->run(function () use ($id) {
			$this->devices->revoke($this->access->requireSessionUid(), $id);
			return ['revoked' => true];
		});
	}
}
