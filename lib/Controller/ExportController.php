<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Controller;

use OCA\TimeGuardian\Service\AccessService;
use OCA\TimeGuardian\Service\ExportService;
use OCA\TimeGuardian\Service\UserConfService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ExportController extends BaseController {
	public function __construct(
		IRequest $request,
		AccessService $access,
		UserConfService $userConf,
		LoggerInterface $logger,
		private ExportService $exports,
	) {
		parent::__construct($request, $access, $userConf, $logger);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function csv(?string $ym = null, string $format = 'detail'): Response {
		return $this->run(function () use ($ym, $format) {
			$f = $this->exports->csvOwn($this->access->requireMember(), $ym, $format);
			return new DataDownloadResponse($f['content'], $f['filename'], 'text/csv; charset=utf-8');
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function json(): Response {
		return $this->run(function () {
			$uid = $this->access->requireSessionUid();
			$data = json_encode($this->exports->jsonOwn($uid), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			return new DataDownloadResponse((string)$data, 'timeguardian-export.json', 'application/json');
		});
	}
}
