<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Controller;

use OCA\TimeGuardian\Service\AccessService;
use OCA\TimeGuardian\Service\EntryService;
use OCA\TimeGuardian\Service\ReportService;
use OCA\TimeGuardian\Service\UserConfService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class EntryController extends BaseController {
	public function __construct(
		IRequest $request,
		AccessService $access,
		UserConfService $userConf,
		LoggerInterface $logger,
		private EntryService $entries,
		private ReportService $reports,
	) {
		parent::__construct($request, $access, $userConf, $logger);
	}

	#[NoAdminRequired]
	public function index(?string $from = null, ?string $to = null): Response {
		return $this->run(function () use ($from, $to) {
			$uid = $this->access->requireMember();
			[$f, $t] = $this->reports->range($from, $to);
			return ['entries' => $this->entries->list($uid, $f, $t)];
		});
	}

	#[NoAdminRequired]
	public function create(): Response {
		return $this->run(fn () => $this->entries->create($this->access->requireMember(), $this->body()), Http::STATUS_CREATED);
	}

	#[NoAdminRequired]
	public function update(int $id): Response {
		return $this->run(fn () => $this->entries->update($this->access->requireMember(), $id, $this->body()));
	}

	#[NoAdminRequired]
	public function destroy(int $id): Response {
		return $this->run(function () use ($id) {
			$this->entries->delete($this->access->requireMember(), $id);
			return ['deleted' => true];
		});
	}
}
