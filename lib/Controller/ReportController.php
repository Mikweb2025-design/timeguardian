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

class ReportController extends BaseController {
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
	public function week(?string $date = null): Response {
		return $this->run(fn () => $this->reports->week($this->access->requireMember(), $date));
	}

	#[NoAdminRequired]
	public function month(?string $ym = null): Response {
		return $this->run(fn () => $this->reports->month($this->access->requireMember(), $ym));
	}

	#[NoAdminRequired]
	public function account(): Response {
		return $this->run(fn () => $this->reports->account($this->access->requireMember()));
	}

	#[NoAdminRequired]
	public function violations(?string $from = null, ?string $to = null): Response {
		return $this->run(function () use ($from, $to) {
			$uid = $this->access->requireMember();
			[$f, $t] = $this->reports->range($from, $to);
			return ['violations' => $this->reports->violationList($uid, $f, $t)];
		});
	}
}
