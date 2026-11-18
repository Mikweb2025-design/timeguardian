<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Settings;

use OCA\TimeGuardian\AppInfo\Application;
use OCA\TimeGuardian\Domain\Holidays;
use OCA\TimeGuardian\Domain\Messages;
use OCA\TimeGuardian\Domain\UiText;
use OCA\TimeGuardian\Service\AccessService;
use OCA\TimeGuardian\Service\ConfigService;
use OCA\TimeGuardian\Service\SetupService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Settings\ISettings;
use OCP\Util;

class Admin implements ISettings {
	public function __construct(
		private ConfigService $config,
		private IL10N $l,
		private IURLGenerator $urls,
		private IRequest $request,
		private SetupService $setup,
		private AccessService $access,
	) {
	}

	private function usersUrl(): string {
		try {
			return $this->urls->linkToRoute('settings.Users.usersList');
		} catch (\Throwable) {
			return $this->urls->getAbsoluteURL('/settings/users');
		}
	}

	/** "[done|open] label: value" line of the status block */
	private function status(bool $ok, string $label, string $value, string $okText, string $todoText): string {
		return '<li><span class="tg-adm-badge ' . ($ok ? 'is-ok' : 'is-todo') . '">' . Form::esc($ok ? $okText : $todoText) . '</span> <strong>' . Form::esc($label) . '</strong> ' . Form::esc($value) . '</li>';
	}

	private function statusBlock(callable $t, string $lang): string {
		$f = $this->setup->facts();
		$cg = $this->config->chefGroup();
		$tg = $this->config->teamGroup();
		$ok = $t('st_ok');
		$todo = $t('st_todo');
		$groupText = function (string $gid, bool $emptyOk) use ($t): string {
			if ($gid === '') {
				return $emptyOk ? $t('st_all_users') : $t('st_none');
			}
			return $gid . ' (' . ($this->access->groupExists($gid) ? $t('st_group_ok') : $t('st_group_missing')) . ')';
		};
		$kiosk = $f['kioskSeenAt'];
		$when = $kiosk === null ? '' : (new \DateTimeImmutable('@' . $kiosk))->setTimezone($this->config->tz())->format($lang === 'en' ? 'Y-m-d H:i' : 'd.m.Y H:i');
		$h = '<ul class="tg-adm-status">';
		$h .= $this->status($f['chefs'] >= 1, $t('st_chefs'), $groupText($cg, false) . ' · ' . $f['chefs'], $ok, $todo);
		$h .= $this->status($f['members'] >= 1, $t('st_members'), $groupText($tg, true) . ' · ' . $f['members'], $ok, $todo);
		$h .= $this->status($f['stateSet'] && $f['targetSet'], $t('st_config'), $f['stateSet'] && $f['targetSet'] ? $t('st_config_ok') : $t('st_config_todo'), $ok, $todo);
		$h .= $this->status($kiosk !== null, $t('st_kiosk'), $kiosk !== null ? str_replace('{when}', $when, $t('st_kiosk_ok')) : $t('st_kiosk_todo'), $ok, $todo);
		$h .= $this->status($f['devices'] >= 1, $t('st_devices'), (string)$f['devices'], $ok, $todo);
		$h .= $this->status($f['punches'] >= 1, $t('st_punches'), (string)$f['punches'], $ok, $todo);
		$h .= '</ul>';
		$h .= '<div class="tg-adm-links"><a class="button primary" href="' . Form::esc($this->urls->linkToRoute('timeguardian.page.index')) . '">' . Form::esc($t('app_link')) . '</a>'
			. '<a class="button" href="' . Form::esc($this->urls->linkToRoute('timeguardian.page.kiosk')) . '">' . Form::esc($t('kiosk_link')) . '</a>'
			. '<a class="button" href="' . Form::esc($this->urls->linkToRoute('timeguardian.page.go')) . '">' . Form::esc($t('go_link')) . '</a></div>';
		return $h;
	}

	public function getForm(): TemplateResponse {
		Util::addStyle(Application::APP_ID, 'tg-admin');
		$lang = Messages::lang($this->l->getLanguageCode());
		$t = fn (string $k) => UiText::t($k, $lang);
		$c = $this->config->toArray();
		$h = '<div id="timeguardian-admin" class="section"><h2>' . Form::esc($t('app')) . '</h2><p class="tg-adm-lead">' . Form::esc($t('admin_intro')) . '</p>';
		$flag = (string)$this->request->getParam('tg', '');
		if ($flag === 'saved') {
			$h .= '<p class="success">' . Form::esc($t('saved')) . '</p>';
		} elseif (str_starts_with($flag, 'error:')) {
			$h .= '<p class="warning">' . Form::esc($t('error') . substr($flag, 6)) . '</p>';
		}
		$h .= Form::section($t('sec_status'), $t('sec_status_p'), $this->statusBlock($t, $lang));
		$h .= '<p class="tg-adm-lead">' . Form::esc($t('chef_note')) . '</p>';
		$h .= '<form method="post" action="' . Form::esc($this->urls->linkToRoute('timeguardian.settings_form.saveAdmin')) . '">';
		$h .= '<input type="hidden" name="requesttoken" value="' . Form::esc(Form::token()) . '">';

		$h .= Form::section($t('sec_roles'), $t('sec_roles_p'),
			Form::row($t('chef_group'), Form::text('chefGroup', $c['chefGroup']), 'tg-chefGroup', $t('h_chef_group'))
			. Form::row($t('team_group'), Form::text('teamGroup', $c['teamGroup']), 'tg-teamGroup', $t('h_team_group'))
			. Form::row($t('kiosk_group'), Form::text('kioskGroup', $c['kioskGroup']), 'tg-kioskGroup', $t('h_kiosk_group'))
			. '<p><a class="button" href="' . Form::esc($this->usersUrl()) . '">' . Form::esc($t('users_link')) . '</a></p>');

		$states = [];
		foreach (Holidays::STATES as $s) {
			$states[$s] = $s;
		}
		$h .= Form::section($t('sec_loc'), '',
			Form::row($t('state'), Form::select('state', $states, $c['state']), 'tg-state', $t('h_state'))
			. Form::row($t('timezone'), Form::text('timezone', $c['timezone']), 'tg-timezone', $t('h_timezone')));

		$days = '';
		$names = ['de' => ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'], 'en' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], 'it' => ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom']][$lang];
		foreach ($names as $i => $n) {
			$days .= '<span>' . Form::check('day' . $i, (bool)(($c['defaultDaysMask'] >> $i) & 1), $n) . '</span>';
		}
		$h .= Form::section($t('sec_work'), '',
			Form::row($t('weekly'), Form::text('defaultWeeklyHours', rtrim(rtrim(number_format($c['defaultWeeklyMin'] / 60, 2, '.', ''), '0'), '.'), 'text', 'inputmode="decimal"'), 'tg-defaultWeeklyHours', $t('h_weekly'))
			. Form::row($t('days'), '<div class="tg-adm-days">' . $days . '</div>', '', $t('h_days')));

		$h .= Form::section($t('sec_keep'), '',
			Form::row($t('retention'), Form::text('retentionDays', (string)$c['retentionDays'], 'number', 'min="30" max="3650"'), 'tg-retentionDays', $t('h_retention'))
			. Form::row($t('projects'), '<textarea id="tg-projects" name="projects" rows="4" cols="40">' . Form::esc(implode("\n", $c['projects'])) . '</textarea>', 'tg-projects', $t('h_projects')));

		$h .= Form::section($t('sec_appr'), '',
			Form::check('selfApproval', $c['selfApproval'], $t('self_approval'), $t('h_self'))
			. Form::check('liveWarnings', $c['liveWarnings'], $t('live_warnings'), $t('h_live'))
			. Form::check('int_notifications', $c['integrations']['notifications'], $t('int_notifications'), $t('h_int_notifications')));

		$h .= Form::section($t('sec_int'), $t('sec_int_p'),
			Form::check('int_calendar', $c['integrations']['calendar'], $t('int_calendar'), $t('h_int_calendar'))
			. Form::check('int_absence_calendar', $c['integrations']['absenceCalendar'], $t('int_absence_calendar'), $t('h_int_absence'))
			. Form::check('int_deck', $c['integrations']['deck'], $t('int_deck'), $t('h_int_deck'))
			. Form::row($t('deck_stack'), Form::text('deckStackId', (string)($c['deckStackId'] ?? ''), 'number', 'min="1"'), 'tg-deckStackId', $t('h_deck_stack'))
			. Form::check('int_talk', $c['integrations']['talk'], $t('int_talk'), $t('h_int_talk'))
			. Form::row($t('talk_room'), Form::text('talkRoomToken', $c['talkRoomToken']), 'tg-talkRoomToken', $t('h_talk_room')));

		$h .= Form::section($t('sec_hook'), $t('sec_hook_p'),
			Form::row($t('webhook_url'), Form::text('webhookUrl', $c['webhookUrl'], 'url'), 'tg-webhookUrl', $t('h_webhook'))
			. Form::row($t('webhook_secret') . ($c['hasWebhookSecret'] ? ' · ' . $t('webhook_set') : ''), Form::text('webhookSecret', '', 'password', 'autocomplete="new-password"'), 'tg-webhookSecret', $t('h_webhook_secret')));

		$h .= '<p class="tg-adm-save"><button type="submit" class="primary">' . Form::esc($t('save')) . '</button></p>';
		$h .= '</form></div>';
		return new HtmlResponse($h);
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 10;
	}
}
