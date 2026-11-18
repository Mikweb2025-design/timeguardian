<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Controller;

use OCA\TimeGuardian\Domain\TgException;
use OCA\TimeGuardian\Service\AccessService;
use OCA\TimeGuardian\Service\DeviceService;
use OCA\TimeGuardian\Service\PunchService;
use OCA\TimeGuardian\Service\StatusService;
use OCA\TimeGuardian\Service\UserConfService;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * The two endpoints that a paired phone may call. Authentication: header X-TG-Device (device token) or the
 * Nextcloud session (then the CSRF token is checked here, because the routes are marked NoCSRFRequired for the
 * token path).
 */
class PunchController extends BaseController {
	public function __construct(
		IRequest $request,
		AccessService $access,
		UserConfService $userConf,
		LoggerInterface $logger,
		private PunchService $punchService,
		private StatusService $statusService,
		private DeviceService $devices,
	) {
		parent::__construct($request, $access, $userConf, $logger);
	}

	/** @return array{0:string,1:?int,2:string} uid, device id, source */
	private function actor(bool $csrf): array {
		$hdr = trim((string)$this->request->getHeader('X-TG-Device'));
		if ($hdr !== '') {
			$d = $this->devices->authenticate($hdr);
			return [$d->getUid(), (int)$d->getId(), 'scan'];
		}
		$uid = $this->access->requireSessionUid();
		if ($csrf && !$this->request->passesCSRFCheck()) {
			throw new TgException('csrf');
		}
		return [$uid, null, 'session'];
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[AnonRateLimit(limit: 60, period: 300)]
	#[UserRateLimit(limit: 60, period: 300)]
	public function punch(): Response {
		return $this->run(function () {
			[$uid, $device, $source] = $this->actor(true);
			return $this->punchService->punch($uid, $this->body(), $source, $device, (string)$this->request->getRemoteAddress());
		});
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[AnonRateLimit(limit: 120, period: 300)]
	#[UserRateLimit(limit: 120, period: 300)]
	public function status(): Response {
		return $this->run(function () {
			[$uid] = $this->actor(false);
			$this->access->assertMember($uid);
			return $this->statusService->status($uid);
		});
	}
}
