<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Settings;

use OCA\TimeGuardian\AppInfo\Application;
use OCA\TimeGuardian\Domain\Dates;
use OCA\TimeGuardian\Domain\Messages;
use OCA\TimeGuardian\Domain\UiText;
use OCA\TimeGuardian\Service\AccessService;
use OCA\TimeGuardian\Service\DeviceService;
use OCA\TimeGuardian\Service\UserConfService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Settings\ISettings;

class Personal implements ISettings {
	public function __construct(
		private UserConfService $userConf,
		private DeviceService $devices,
		private AccessService $access,
		private IL10N $l,
		private IURLGenerator $urls,
		private IRequest $request,
	) {
	}

	public function getForm(): TemplateResponse {
		$uid = $this->access->sessionUid();
		$lang = Messages::lang($this->l->getLanguageCode());
		$t = fn (string $k) => UiText::t($k, $lang);
		if ($uid === null || !$this->access->policy()->isTeamMember($uid)) {
			return new HtmlResponse('');
		}
		$c = $this->userConf->get($uid);
		$token = Form::esc(Form::token());
		$h = '<div id="timeguardian-personal" class="section"><h2>' . Form::esc($t('app')) . '</h2><p class="settings-hint">' . Form::esc($t('personal_intro')) . '</p>';
		if ((string)$this->request->getParam('tg', '') === 'saved') {
			$h .= '<p class="success">' . Form::esc($t('saved')) . '</p>';
		}
		$h .= '<form method="post" action="' . Form::esc($this->urls->linkToRoute('timeguardian.settings_form.savePersonal')) . '">';
		$h .= '<input type="hidden" name="requesttoken" value="' . $token . '">';
		$h .= Form::row($t('mode'), Form::select('mode', ['simple' => $t('mode_simple'), 'pause' => $t('mode_pause')], $c['mode']), 'tg-mode');
		$h .= Form::row($t('lang'), Form::select('lang', ['' => $t('lang_auto'), 'de' => 'Deutsch', 'en' => 'English', 'it' => 'Italiano'], (string)($c['lang'] ?? '')), 'tg-lang');
		$h .= Form::row($t('target'), '<span>' . Form::esc(Dates::fmtH($c['weeklyMin'], $lang)) . '</span>');
		$h .= '<p><button type="submit" class="primary">' . Form::esc($t('save')) . '</button> <a class="button" href="' . Form::esc($this->urls->linkToRoute('timeguardian.page.index')) . '">' . Form::esc($t('app_link')) . '</a></p></form>';
		$h .= '<h3>' . Form::esc($t('devices')) . '</h3>';
		$list = $this->devices->list($uid);
		if ($list === []) {
			$h .= '<p>' . Form::esc($t('no_devices')) . '</p>';
		}
		foreach ($list as $d) {
			$h .= '<form method="post" style="margin-bottom:6px" action="' . Form::esc($this->urls->linkToRoute('timeguardian.settings_form.revokeDevice', ['id' => $d['id']])) . '">'
				. '<input type="hidden" name="requesttoken" value="' . $token . '"><strong>' . Form::esc($d['label']) . '</strong> · '
				. Form::esc($t('last_used')) . ' ' . Form::esc(substr($d['lastUsed'], 0, 10)) . ' ';
			$h .= $d['revoked'] ? '<em>' . Form::esc($t('revoked')) . '</em>' : '<button type="submit">' . Form::esc($t('revoke')) . '</button>';
			$h .= '</form>';
		}
		return new HtmlResponse($h . '</div>');
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 10;
	}
}
