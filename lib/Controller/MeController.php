<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Controller;

use OCA\TimeGuardian\Service\AccessService;
use OCA\TimeGuardian\Service\ReportService;
use OCA\TimeGuardian\Service\UserConfService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class MeController extends BaseController {
	public function __construct(
		IRequest $request,
		AccessService $access,
		UserConfService $userConf,
		LoggerInterface $logger,
		private ReportService $reports,
	) {
		parent::__construct($request, $access, $userConf, $logger);
	}

	#[NoAdminRequired]
	public function me(): Response {
		return $this->run(fn () => $this->reports->me($this->access->requireMember()));
	}

	private function confView(string $uid): array {
		$c = $this->userConf->get($uid);
		return ['weeklyMin' => $c['weeklyMin'], 'daysMask' => $c['daysMask'], 'mode' => $c['mode'], 'lang' => $c['lang'], 'canSetTarget' => $this->access->isChef($uid)];
	}

	#[NoAdminRequired]
	public function settings(): Response {
		return $this->run(fn () => $this->confView($this->access->requireMember()));
	}

	#[NoAdminRequired]
	public function updateSettings(): Response {
		return $this->run(function () {
			$uid = $this->access->requireMember();
			$this->userConf->updateOwn($uid, $this->body());
			return $this->confView($uid);
		});
	}
}
