<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Controller;

use OCA\TimeGuardian\Service\AbsenceService;
use OCA\TimeGuardian\Service\AccessService;
use OCA\TimeGuardian\Service\UserConfService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class AbsenceController extends BaseController {
	public function __construct(
		IRequest $request,
		AccessService $access,
		UserConfService $userConf,
		LoggerInterface $logger,
		private AbsenceService $absences,
	) {
		parent::__construct($request, $access, $userConf, $logger);
	}

	#[NoAdminRequired]
	public function index(string $scope = 'mine', ?string $from = null, ?string $to = null): Response {
		return $this->run(fn () => ['absences' => $this->absences->list($this->access->requireMember(), $scope, $from, $to)]);
	}

	#[NoAdminRequired]
	public function create(): Response {
		return $this->run(fn () => ['absence' => $this->absences->create($this->access->requireMember(), $this->body())], Http::STATUS_CREATED);
	}

	#[NoAdminRequired]
	public function destroy(int $id): Response {
		return $this->run(function () use ($id) {
			$this->absences->delete($this->access->requireMember(), $id);
			return ['deleted' => true];
		});
	}

	#[NoAdminRequired]
	public function decide(int $id): Response {
		return $this->run(function () use ($id) {
			$p = $this->body();
			return ['absence' => $this->absences->decide($this->access->requireChef(), $id, $p['decision'] ?? null, $p['note'] ?? null)];
		});
	}
}
