<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Service;

use OCA\TimeGuardian\AppInfo\Application;
use OCA\TimeGuardian\Domain\Holidays;
use OCA\TimeGuardian\Domain\QrSigner;
use OCA\TimeGuardian\Domain\Sanitize;
use OCA\TimeGuardian\Domain\TgException;
use OCA\TimeGuardian\Domain\Tz;
use OCA\TimeGuardian\Domain\Validator;
use OCA\TimeGuardian\Domain\Webhook;
use OCP\IAppConfig;

/** Admin configuration (app config values, all stored as strings). */
class ConfigService {
	private const APP = Application::APP_ID;

	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	private function str(string $key, string $default = ''): string {
		return $this->appConfig->getValueString(self::APP, $key, $default);
	}

	private function set(string $key, string $value, bool $sensitive = false): void {
		$this->appConfig->setValueString(self::APP, $key, $value, false, $sensitive);
	}

	/** has the value ever been stored (used by the setup checklist: "chosen" vs. "still the default") */
	public function hasKey(string $key): bool {
		return $this->appConfig->hasKey(self::APP, $key, null);
	}

	/** unix time when a kiosk page was last seen polling, null = never */
	public function kioskSeenAt(): ?int {
		$v = (int)$this->str('kiosk_seen_at', '0');
		return $v > 0 ? $v : null;
	}

	/** remembers that a kiosk is open (written at most every 10 minutes) */
	public function touchKioskSeen(int $now): void {
		$last = $this->kioskSeenAt();
		if ($last === null || $now - $last >= 600) {
			$this->set('kiosk_seen_at', (string)$now);
		}
	}

	public function chefGroup(): string {
		return $this->str('chef_group', 'tg-chef');
	}

	public function teamGroup(): string {
		return $this->str('team_group', 'tg-team');
	}

	public function kioskGroup(): string {
		return $this->str('kiosk_group', '');
	}

	public function state(): string {
		$s = strtoupper($this->str('state', 'BE'));
		return in_array($s, Holidays::STATES, true) ? $s : 'BE';
	}

	public function timezoneName(): string {
		$n = $this->str('timezone', Tz::DEFAULT);
		return Tz::isValidName($n) ? $n : Tz::DEFAULT;
	}

	public function tz(): \DateTimeZone {
		return Tz::zone($this->timezoneName());
	}

	public function defaultWeeklyMin(): int {
		return (Validator::weeklyMin((int)$this->str('default_weekly_min', '2400')) ?? 2400);
	}

	public function defaultDaysMask(): int {
		return (Validator::daysMask((int)$this->str('default_days_mask', '31')) ?? 31);
	}

	public function retentionDays(): int {
		$d = (int)$this->str('retention_days', '730');
		return $d >= 30 && $d <= 3650 ? $d : 730;
	}

	/** @return list<string> */
	public function projects(): array {
		$j = json_decode($this->str('projects', '[]'), true);
		if (!is_array($j)) {
			return [];
		}
		$out = [];
		foreach ($j as $p) {
			$t = Sanitize::text($p, 64);
			if ($t !== '' && !in_array($t, $out, true)) {
				$out[] = $t;
			}
		}
		return array_slice($out, 0, 100);
	}

	public function selfApproval(): bool {
		return $this->str('self_approval', '0') === '1';
	}

	public function liveWarnings(): bool {
		return $this->str('live_warnings', '1') === '1';
	}

	public function integration(string $name): bool {
		$default = ['notifications' => '1', 'calendar' => '0', 'absence_calendar' => '1', 'deck' => '0', 'talk' => '0'][$name] ?? '0';
		return $this->str('int_' . $name, $default) === '1';
	}

	public function deckStackId(): ?int {
		$v = (int)$this->str('deck_stack_id', '');
		return $v > 0 ? $v : null;
	}

	public function talkRoomToken(): string {
		return $this->str('talk_room_token', '');
	}

	public function webhookUrl(): string {
		return $this->str('webhook_url', '');
	}

	public function webhookSecret(): string {
		return $this->str('webhook_secret', '');
	}

	/** instance secret for the kiosk QR codes, created on first use */
	public function qrSecret(): string {
		$s = $this->str('qr_secret', '');
		if (strlen($s) < 32) {
			$s = QrSigner::newSecret();
			$this->set('qr_secret', $s, true);
		}
		return $s;
	}

	public function lastWeekly(): string {
		return $this->str('last_weekly', '');
	}

	public function setLastWeekly(string $isoWeek): void {
		$this->set('last_weekly', $isoWeek);
	}

	/** the admin facing view (the webhook secret is never returned) */
	public function toArray(): array {
		return [
			'chefGroup' => $this->chefGroup(),
			'teamGroup' => $this->teamGroup(),
			'kioskGroup' => $this->kioskGroup(),
			'state' => $this->state(),
			'timezone' => $this->timezoneName(),
			'defaultWeeklyMin' => $this->defaultWeeklyMin(),
			'defaultDaysMask' => $this->defaultDaysMask(),
			'retentionDays' => $this->retentionDays(),
			'projects' => $this->projects(),
			'selfApproval' => $this->selfApproval(),
			'liveWarnings' => $this->liveWarnings(),
			'integrations' => [
				'notifications' => $this->integration('notifications'),
				'calendar' => $this->integration('calendar'),
				'absenceCalendar' => $this->integration('absence_calendar'),
				'deck' => $this->integration('deck'),
				'talk' => $this->integration('talk'),
			],
			'deckStackId' => $this->deckStackId(),
			'talkRoomToken' => $this->talkRoomToken(),
			'webhookUrl' => $this->webhookUrl(),
			'hasWebhookSecret' => $this->webhookSecret() !== '',
		];
	}

	private function groupOk(mixed $g, bool $mayBeEmpty): ?string {
		if (!is_string($g)) {
			return null;
		}
		$g = trim($g);
		if ($g === '') {
			return $mayBeEmpty ? '' : null;
		}
		return mb_strlen($g) <= 64 ? $g : null;
	}

	/**
	 * Validates and stores a subset of the configuration.
	 * @throws TgException validation with the offending keys in fields
	 */
	public function update(array $in): void {
		$bad = [];
		$todo = [];
		$take = function (string $key, callable $check, string $store, ?callable $fmt = null) use ($in, &$bad, &$todo): void {
			if (!array_key_exists($key, $in)) {
				return;
			}
			$v = $check($in[$key]);
			if ($v === null) {
				$bad[] = $key;
				return;
			}
			$todo[$store] = $fmt ? $fmt($v) : (string)$v;
		};
		$take('chefGroup', fn ($v) => $this->groupOk($v, false), 'chef_group');
		$take('teamGroup', fn ($v) => $this->groupOk($v, true), 'team_group');
		$take('kioskGroup', fn ($v) => $this->groupOk($v, true), 'kiosk_group');
		$take('state', fn ($v) => (is_string($v) && in_array(strtoupper($v), Holidays::STATES, true)) ? strtoupper($v) : null, 'state');
		$take('timezone', fn ($v) => (is_string($v) && Tz::isValidName($v)) ? $v : null, 'timezone');
		$take('defaultWeeklyMin', fn ($v) => Validator::weeklyMin($v), 'default_weekly_min');
		$take('defaultDaysMask', fn ($v) => Validator::daysMask($v), 'default_days_mask');
		$take('retentionDays', function ($v) {
			if (is_string($v) && ctype_digit($v)) {
				$v = (int)$v;
			}
			return (is_int($v) && $v >= 30 && $v <= 3650) ? $v : null;
		}, 'retention_days');
		$take('projects', function ($v) {
			if (is_string($v)) {
				$v = preg_split('/[\r\n,]+/', $v) ?: [];
			}
			if (!is_array($v) || count($v) > 100) {
				return null;
			}
			$out = [];
			foreach ($v as $p) {
				$t = Sanitize::text($p, 64);
				if ($t !== '' && !in_array($t, $out, true)) {
					$out[] = $t;
				}
			}
			return $out;
		}, 'projects', fn ($v) => json_encode($v, JSON_UNESCAPED_UNICODE));
		foreach (['selfApproval' => 'self_approval', 'liveWarnings' => 'live_warnings'] as $k => $store) {
			$take($k, fn ($v) => is_bool($v) ? $v : (in_array($v, ['0', '1', 0, 1], true) ? (bool)$v : null), $store, fn ($v) => $v ? '1' : '0');
		}
		if (array_key_exists('integrations', $in)) {
			$i = $in['integrations'];
			if (!is_array($i)) {
				$bad[] = 'integrations';
			} else {
				foreach (['notifications' => 'notifications', 'calendar' => 'calendar', 'absenceCalendar' => 'absence_calendar', 'deck' => 'deck', 'talk' => 'talk'] as $k => $name) {
					if (array_key_exists($k, $i)) {
						if (!is_bool($i[$k]) && !in_array($i[$k], ['0', '1', 0, 1], true)) {
							$bad[] = 'integrations.' . $k;
						} else {
							$todo['int_' . $name] = $i[$k] ? '1' : '0';
						}
					}
				}
			}
		}
		$take('deckStackId', function ($v) {
			if ($v === null || $v === '') {
				return 0;
			}
			if (is_string($v) && ctype_digit($v)) {
				$v = (int)$v;
			}
			return (is_int($v) && $v >= 0) ? $v : null;
		}, 'deck_stack_id', fn ($v) => $v > 0 ? (string)$v : '');
		$take('talkRoomToken', fn ($v) => (is_string($v) && preg_match('/^[a-z0-9]{0,32}$/D', $v)) ? $v : null, 'talk_room_token');
		$take('webhookUrl', fn ($v) => (is_string($v) && Webhook::validUrl(trim($v))) ? trim($v) : null, 'webhook_url');
		$secret = null;
		if (array_key_exists('webhookSecret', $in)) {
			if (!is_string($in['webhookSecret']) || strlen($in['webhookSecret']) > 200) {
				$bad[] = 'webhookSecret';
			} else {
				$secret = $in['webhookSecret'];
			}
		}
		$url = $todo['webhook_url'] ?? $this->webhookUrl();
		if ($url !== '' && $secret === null && $this->webhookSecret() === '') {
			$bad[] = 'webhookSecret';
		}
		if ($url !== '' && $secret === '') {
			$bad[] = 'webhookSecret';
		}
		if ($bad !== []) {
			throw new TgException('validation', $bad);
		}
		foreach ($todo as $k => $v) {
			$this->set($k, $v);
		}
		if ($secret !== null) {
			$this->set('webhook_secret', $secret, true);
		}
	}
}
