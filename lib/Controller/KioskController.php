<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Controller;

use OCA\TimeGuardian\Domain\TgException;
use OCA\TimeGuardian\Service\AccessService;
use OCA\TimeGuardian\Service\ConfigService;
use OCA\TimeGuardian\Service\QrService;
use OCA\TimeGuardian\Service\UserConfService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class KioskController extends BaseController {
	public function __construct(
		IRequest $request,
		AccessService $access,
		UserConfService $userConf,
		LoggerInterface $logger,
		private QrService $qr,
		private ConfigService $config,
		private ITimeFactory $time,
	) {
		parent::__construct($request, $access, $userConf, $logger);
	}

	#[NoAdminRequired]
	public function code(): Response {
		return $this->run(function () {
			$uid = $this->access->requireSessionUid();
			if (!$this->access->canKiosk($uid)) {
				throw new TgException('forbidden');
			}
			$this->config->touchKioskSeen($this->time->getTime());
			$r = new JSONResponse($this->qr->current());
			$r->addHeader('Cache-Control', 'no-store');
			return $r;
		});
	}
}
