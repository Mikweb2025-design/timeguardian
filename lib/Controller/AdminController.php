<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Controller;

use OCA\TimeGuardian\Service\AccessService;
use OCA\TimeGuardian\Service\ConfigService;
use OCA\TimeGuardian\Service\UserConfService;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/** Admin only: no #[NoAdminRequired], Nextcloud enforces the admin check. */
class AdminController extends BaseController {
	public function __construct(
		IRequest $request,
		AccessService $access,
		UserConfService $userConf,
		LoggerInterface $logger,
		private ConfigService $config,
	) {
		parent::__construct($request, $access, $userConf, $logger);
	}

	public function getConfig(): Response {
		return $this->run(fn () => $this->config->toArray());
	}

	public function setConfig(): Response {
		return $this->run(function () {
			$this->config->update($this->body());
			return $this->config->toArray();
		});
	}
}
