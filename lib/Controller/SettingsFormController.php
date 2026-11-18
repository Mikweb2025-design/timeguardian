<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Controller;

use OCA\TimeGuardian\AppInfo\Application;
use OCA\TimeGuardian\Domain\TgException;
use OCA\TimeGuardian\Service\AccessService;
use OCA\TimeGuardian\Service\ConfigService;
use OCA\TimeGuardian\Service\DeviceService;
use OCA\TimeGuardian\Service\UserConfService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IRequest;
use OCP\IURLGenerator;

/** Targets of the server rendered settings forms; always answer with a redirect back to the settings page. */
class SettingsFormController extends Controller {
	public function __construct(
		IRequest $request,
		private ConfigService $config,
		private UserConfService $userConf,
		private DeviceService $devices,
		private AccessService $access,
		private IURLGenerator $urls,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	private function back(string $route, string $flag): RedirectResponse {
		return new RedirectResponse($this->urls->linkToRoute($route, ['section' => Application::APP_ID]) . '?tg=' . rawurlencode($flag));
	}

	/** admin only (no #[NoAdminRequired]) */
	public function saveAdmin(): RedirectResponse {
		$p = $this->request->getParams();
		$on = fn (string $k): bool => isset($p[$k]) && $p[$k] === '1';
		$mask = 0;
		for ($i = 0; $i < 7; $i++) {
			$mask |= $on('day' . $i) ? (1 << $i) : 0;
		}
		$in = [
			'chefGroup' => (string)($p['chefGroup'] ?? ''),
			'teamGroup' => (string)($p['teamGroup'] ?? ''),
			'kioskGroup' => (string)($p['kioskGroup'] ?? ''),
			'state' => (string)($p['state'] ?? ''),
			'timezone' => (string)($p['timezone'] ?? ''),
			'defaultWeeklyMin' => (int)round((float)str_replace(',', '.', (string)($p['defaultWeeklyHours'] ?? '40')) * 60),
			'defaultDaysMask' => $mask,
			'retentionDays' => (string)($p['retentionDays'] ?? ''),
			'projects' => (string)($p['projects'] ?? ''),
			'selfApproval' => $on('selfApproval'),
			'liveWarnings' => $on('liveWarnings'),
			'integrations' => [
				'notifications' => $on('int_notifications'),
				'calendar' => $on('int_calendar'),
				'absenceCalendar' => $on('int_absence_calendar'),
				'deck' => $on('int_deck'),
				'talk' => $on('int_talk'),
			],
			'deckStackId' => (string)($p['deckStackId'] ?? ''),
			'talkRoomToken' => (string)($p['talkRoomToken'] ?? ''),
			'webhookUrl' => (string)($p['webhookUrl'] ?? ''),
		];
		if (isset($p['webhookSecret']) && $p['webhookSecret'] !== '') {
			$in['webhookSecret'] = (string)$p['webhookSecret'];
		}
		try {
			$this->config->update($in);
		} catch (TgException $e) {
			return $this->back('settings.AdminSettings.index', 'error:' . implode(',', $e->fields()));
		}
		return $this->back('settings.AdminSettings.index', 'saved');
	}

	#[NoAdminRequired]
	public function savePersonal(): RedirectResponse {
		try {
			$uid = $this->access->requireMember();
			$p = $this->request->getParams();
			$this->userConf->updateOwn($uid, ['mode' => $p['mode'] ?? null, 'lang' => $p['lang'] ?? '']);
		} catch (TgException) {
			return $this->back('settings.PersonalSettings.index', 'error');
		}
		return $this->back('settings.PersonalSettings.index', 'saved');
	}

	#[NoAdminRequired]
	public function revokeDevice(int $id): RedirectResponse {
		try {
			$this->devices->revoke($this->access->requireSessionUid(), $id);
		} catch (TgException) {
			return $this->back('settings.PersonalSettings.index', 'error');
		}
		return $this->back('settings.PersonalSettings.index', 'saved');
	}
}
