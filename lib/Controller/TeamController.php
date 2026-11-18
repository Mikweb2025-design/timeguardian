<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Controller;

use OCA\TimeGuardian\Domain\TgException;
use OCA\TimeGuardian\Service\AccessService;
use OCA\TimeGuardian\Service\AuditService;
use OCA\TimeGuardian\Service\ExportService;
use OCA\TimeGuardian\Service\ReportService;
use OCA\TimeGuardian\Service\UserConfService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class TeamController extends BaseController {
	public function __construct(
		IRequest $request,
		AccessService $access,
		UserConfService $userConf,
		LoggerInterface $logger,
		private ReportService $reports,
		private ExportService $exports,
		private AuditService $auditService,
	) {
		parent::__construct($request, $access, $userConf, $logger);
	}

	#[NoAdminRequired]
	public function overview(?string $week = null): Response {
		return $this->run(fn () => $this->reports->teamOverview($this->access->requireChef(), $week));
	}

	#[NoAdminRequired]
	public function person(string $uid, ?string $from = null, ?string $to = null): Response {
		return $this->run(function () use ($uid, $from, $to) {
			$actor = $this->access->requireMember();
			$this->access->assertCanView($actor, $uid);
			[$f, $t] = $this->reports->range($from, $to);
			return $this->reports->person($actor, $uid, $f, $t);
		});
	}

	#[NoAdminRequired]
	public function setTarget(string $uid): Response {
		return $this->run(function () use ($uid) {
			$actor = $this->access->requireChef();
			if (!$this->access->policy()->canSetTarget($actor, $uid)) {
				throw new TgException('not_found');
			}
			$c = $this->userConf->setTarget($uid, $this->body());
			return ['conf' => ['weeklyMin' => $c['weeklyMin'], 'daysMask' => $c['daysMask'], 'mode' => $c['mode'], 'lang' => $c['lang']]];
		});
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function export(?string $ym = null, string $format = 'detail'): Response {
		return $this->run(function () use ($ym, $format) {
			$f = $this->exports->csvTeam($this->access->requireChef(), $ym, $format);
			return new DataDownloadResponse($f['content'], $f['filename'], 'text/csv; charset=utf-8');
		});
	}

	#[NoAdminRequired]
	public function audit(?string $uid = null, ?string $from = null, ?string $to = null): Response {
		return $this->run(function () use ($uid, $from, $to) {
			$actor = $this->access->requireMember();
			$target = ($uid === null || $uid === '') ? $actor : $uid;
			$this->access->assertCanView($actor, $target);
			[$f, $t] = $this->reports->range($from, $to, 90);
			return ['audit' => $this->auditService->list($target, $f, $t)];
		});
	}
}
