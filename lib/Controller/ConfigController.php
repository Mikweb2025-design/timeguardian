<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Controller;

use OCA\TimeGuardian\Domain\ConfigAccess;
use OCA\TimeGuardian\Domain\LiveWarnings;
use OCA\TimeGuardian\Domain\TgException;
use OCA\TimeGuardian\Service\AccessService;
use OCA\TimeGuardian\Service\ConfigService;
use OCA\TimeGuardian\Service\SetupService;
use OCA\TimeGuardian\Service\UserConfService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * The configuration page of the app for managers (who are not necessarily Nextcloud admins). Managers may read
 * everything and write everything except the three group names, which stay with Nextcloud administrators.
 */
class ConfigController extends BaseController {
	public function __construct(
		IRequest $request,
		AccessService $access,
		UserConfService $userConf,
		LoggerInterface $logger,
		private ConfigService $config,
		private SetupService $setup,
	) {
		parent::__construct($request, $access, $userConf, $logger);
	}

	private function view(string $uid): array {
		return [
			'config' => $this->config->toArray(),
			'locked' => ConfigAccess::ADMIN_ONLY,
			'canEditGroups' => $this->access->isAdmin($uid),
			'roles' => $this->setup->roles($uid),
			'thresholds' => ['warnDayMin' => LiveWarnings::DAY10_AT, 'warnBreakMin' => LiveWarnings::BREAK_AT, 'forgottenMin' => LiveWarnings::FORGOT_AT, 'limitDayMin' => 600],
		];
	}

	#[NoAdminRequired]
	public function show(): Response {
		return $this->run(fn () => $this->view($this->access->requireChef()));
	}

	#[NoAdminRequired]
	public function update(): Response {
		return $this->run(function () {
			$uid = $this->access->requireChef();
			$in = $this->body();
			if (!ConfigAccess::mayWrite($in, $this->access->isAdmin($uid))) {
				throw new TgException('forbidden', ConfigAccess::adminOnlyKeys($in));
			}
			$this->config->update($in);
			return $this->view($uid);
		});
	}
}
