<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Controller;

use OCA\TimeGuardian\Service\AccessService;
use OCA\TimeGuardian\Service\CorrectionService;
use OCA\TimeGuardian\Service\ReportService;
use OCA\TimeGuardian\Service\UserConfService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class CorrectionController extends BaseController {
	public function __construct(
		IRequest $request,
		AccessService $access,
		UserConfService $userConf,
		LoggerInterface $logger,
		private CorrectionService $corrections,
		private ReportService $reports,
	) {
		parent::__construct($request, $access, $userConf, $logger);
	}

	#[NoAdminRequired]
	public function index(string $scope = 'mine', ?string $status = null): Response {
		return $this->run(fn () => ['corrections' => $this->corrections->list($this->access->requireMember(), $scope, $status)]);
	}

	#[NoAdminRequired]
	public function create(): Response {
		return $this->run(fn () => ['correction' => $this->corrections->create($this->access->requireMember(), $this->body())], Http::STATUS_CREATED);
	}

	#[NoAdminRequired]
	public function decide(int $id): Response {
		return $this->run(function () use ($id) {
			$p = $this->body();
			$r = $this->corrections->decide($this->access->requireChef(), $id, $p['decision'] ?? null, $p['note'] ?? null);
			return ['correction' => $r['correction'], 'entry' => $r['entry'] === null ? null : $this->reports->entryView($r['entry'])];
		});
	}
}
