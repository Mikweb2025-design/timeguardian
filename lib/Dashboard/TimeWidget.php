<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Dashboard;

use OCA\TimeGuardian\AppInfo\Application;
use OCA\TimeGuardian\Domain\Dates;
use OCA\TimeGuardian\Domain\Messages;
use OCA\TimeGuardian\Domain\Tz;
use OCA\TimeGuardian\Service\AccessService;
use OCA\TimeGuardian\Service\ConfigService;
use OCA\TimeGuardian\Service\ReportService;
use OCA\TimeGuardian\Service\StatusService;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\IButtonWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\IReloadableWidget;
use OCP\Dashboard\Model\WidgetButton;
use OCP\Dashboard\Model\WidgetItem;
use OCP\Dashboard\Model\WidgetItems;
use OCP\IL10N;
use OCP\IURLGenerator;

/**
 * Dashboard widget "week + team". Rendered natively by the dashboard from items (no JavaScript of its own), read
 * directly from the database through the same services as the app.
 */
class TimeWidget implements IAPIWidgetV2, IButtonWidget, IIconWidget, IReloadableWidget {
	public function __construct(
		private IL10N $l,
		private IURLGenerator $urls,
		private ReportService $reports,
		private StatusService $status,
		private AccessService $access,
		private ConfigService $config,
	) {
	}

	public function getId(): string {
		return 'timeguardian';
	}

	public function getTitle(): string {
		return $this->l->t('TimeGuardian');
	}

	public function getOrder(): int {
		return 6;
	}

	public function getIconClass(): string {
		return 'icon-timeguardian';
	}

	public function getIconUrl(): string {
		return $this->urls->getAbsoluteURL($this->urls->imagePath(Application::APP_ID, 'app.svg'));
	}

	public function getUrl(): ?string {
		return $this->urls->linkToRouteAbsolute('timeguardian.page.index');
	}

	public function load(): void {
	}

	public function getReloadInterval(): int {
		return 120;
	}

	public function getWidgetButtons(string $userId): array {
		return [new WidgetButton(WidgetButton::TYPE_MORE, $this->urls->linkToRouteAbsolute('timeguardian.page.index'), $this->l->t('Open TimeGuardian'))];
	}

	public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
		$lang = Messages::lang($this->l->getLanguageCode());
		$link = $this->urls->linkToRouteAbsolute('timeguardian.page.index');
		$icon = $this->getIconUrl();
		$empty = Messages::misc('w_empty', $lang);
		try {
			if (!$this->access->policy()->isTeamMember($userId)) {
				return new WidgetItems(emptyMessage: $empty);
			}
			$tz = $this->config->tz();
			$st = $this->status->status($userId);
			$week = $this->reports->week($userId, null);
			$acc = $this->reports->account($userId);
			$items = [];
			$stateText = match ($st['state']) {
				'working' => Messages::misc('w_state_working', $lang, ['t' => $st['since'] === null ? '' : Tz::hm((int)Dates::parseIso($st['since']), $tz)]),
				'pause' => Messages::misc('w_state_pause', $lang),
				'done' => Messages::misc('w_state_done', $lang),
				default => Messages::misc('w_state_none', $lang),
			};
			$items[] = new WidgetItem(title: $stateText, subtitle: Messages::misc('w_today', $lang, ['net' => Dates::fmtH($st['netMinToday'], $lang)]), link: $link, iconUrl: $icon, sinceId: 'state');
			$items[] = new WidgetItem(
				title: Messages::misc('w_week', $lang, ['kw' => $week['kw'], 'net' => Dates::fmtH($week['netMin'], $lang), 'target' => Dates::fmtH($week['targetMin'], $lang)]),
				subtitle: Messages::misc('w_balance', $lang, ['bal' => ($acc['balanceMin'] > 0 ? '+' : '') . Dates::fmtH($acc['balanceMin'], $lang)]),
				link: $link, iconUrl: $icon, sinceId: 'week',
			);
			foreach (array_slice($week['violations'], 0, 3) as $i => $v) {
				$items[] = new WidgetItem(title: $v['text'], subtitle: $v['day'], link: $link, iconUrl: $icon, sinceId: 'v' . $i);
			}
			if ($this->access->isChef($userId)) {
				$ov = $this->reports->teamOverview($userId, null);
				$viol = 0;
				foreach ($ov['people'] as $p) {
					$viol += $p['violations']['verstoss'];
				}
				$pending = $ov['pending']['absences'] + $ov['pending']['corrections'];
				$items[] = new WidgetItem(
					title: Messages::misc('w_team', $lang, ['n' => count($ov['presentNow']), 'v' => $viol]),
					subtitle: $pending > 0 ? Messages::misc('w_pending', $lang, ['n' => $pending]) : '',
					link: $link . '#/team', iconUrl: $icon, sinceId: 'team',
				);
			}
			return new WidgetItems(items: array_slice($items, 0, max(1, $limit)), emptyMessage: $empty);
		} catch (\Throwable) {
			return new WidgetItems(emptyMessage: $empty);
		}
	}
}
