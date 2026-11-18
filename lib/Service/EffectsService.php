<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Service;

use OCA\TimeGuardian\AppInfo\Application;
use OCA\TimeGuardian\Db\Absence;
use OCA\TimeGuardian\Db\Correction;
use OCA\TimeGuardian\Domain\Messages;
use OCA\TimeGuardian\Domain\Rules;
use OCA\TimeGuardian\Integration\CalendarService;
use OCA\TimeGuardian\Integration\DeckService;
use OCA\TimeGuardian\Integration\NotificationService;
use OCA\TimeGuardian\Integration\TalkService;
use OCA\TimeGuardian\Integration\WebhookService;
use Psr\Log\LoggerInterface;

/**
 * Everything that happens "because of" a booking: notifications, calendar, Deck, Talk, webhook.
 * Each step runs in its own try/catch: these side effects must never make a booking fail.
 */
class EffectsService {
	public function __construct(
		private NotificationService $notifications,
		private CalendarService $calendar,
		private DeckService $deck,
		private TalkService $talk,
		private WebhookService $webhook,
		private AccessService $access,
		private UserConfService $userConf,
		private ConfigService $config,
		private LoggerInterface $logger,
	) {
	}

	private function safe(string $what, callable $fn): void {
		try {
			$fn();
		} catch (\Throwable $e) {
			$this->logger->warning('TimeGuardian side effect "' . $what . '" failed: ' . $e->getMessage(), ['app' => Application::APP_ID, 'exception' => $e]);
		}
	}

	/** @return list<string> managers who should hear about a request of $uid */
	private function decidersFor(string $uid): array {
		$out = [];
		foreach ($this->access->chefUids() as $c) {
			if ($c !== $uid || $this->config->selfApproval()) {
				$out[] = $c;
			}
		}
		return $out;
	}

	/** @param list<array> $new freshly detected rule hits of one user */
	public function violations(string $uid, array $new): void {
		if ($new === []) {
			return;
		}
		$name = $this->access->displayName($uid);
		$chefs = $this->access->chefUids();
		$chefLang = $chefs === [] ? 'de' : $this->userConf->langOf($chefs[0]);
		foreach ($new as $v) {
			$this->safe('webhook violation', fn () => $this->webhook->emit('violation', ['uid' => $uid, 'name' => $name, 'day' => $v['day'], 'rule' => $v['rule'], 'level' => $v['level'], 'value' => $v['value']]));
			if ($v['level'] !== Rules::LEVEL_VIOLATION) {
				continue;
			}
			$key = md5($uid . '|' . $v['day'] . '|' . $v['rule']);
			$this->safe('notify violation', fn () => $this->notifications->send($uid, 'violation_user', ['rule' => $v['rule'], 'value' => $v['value'], 'day' => $v['day']], 'violation', $key, 'timeguardian.page.index', '#/week'));
			$text = Rules::text($v, $chefLang);
			$this->safe('deck violation', fn () => $this->deck->violationCard(
				Messages::misc('deck_title', $chefLang, ['name' => $name, 'day' => $v['day'], 'rule' => $v['rule']]),
				$name . ' · ' . $v['day'] . "\n\n" . $text . "\n\n" . Messages::misc('auto', $chefLang),
				$chefs[0] ?? $uid,
			));
			$this->safe('talk violation', fn () => $this->talk->post(Messages::misc('talk_violation', $chefLang, ['name' => $name, 'day' => $v['day'], 'text' => $text])));
		}
	}

	public function punched(string $uid, string $action, string $state, ?int $entryId): void {
		$this->safe('webhook punch', fn () => $this->webhook->emit('punch', ['uid' => $uid, 'name' => $this->access->displayName($uid), 'action' => $action, 'state' => $state, 'entryId' => $entryId]));
	}

	public function entrySaved(array $entryRow): void {
		$this->safe('calendar entry', fn () => $this->calendar->syncEntry($entryRow, $this->userConf->langOf((string)$entryRow['uid'])));
	}

	public function entryRemoved(string $uid, int $entryId): void {
		$this->safe('calendar entry removal', fn () => $this->calendar->removeEntry($uid, $entryId));
	}

	public function absenceRequested(Absence $a): void {
		$name = $this->access->displayName($a->getUid());
		foreach ($this->decidersFor($a->getUid()) as $chef) {
			$this->safe('notify absence request', fn () => $this->notifications->send($chef, 'absence_request', [
				'name' => $name, 'kind' => $a->getKind(), 'from' => $a->getFromDay(), 'to' => $a->getToDay(),
			], 'absence', (string)$a->getId(), 'timeguardian.page.index', '#/absences'));
		}
	}

	/** returns the calendar event uid (when one was written) */
	public function absenceDecided(Absence $a, string $decider): ?string {
		$name = $this->access->displayName($a->getUid());
		$eventUid = null;
		$this->safe('dismiss absence request', fn () => $this->notifications->dismissForAll($this->access->chefUids(), 'absence', (string)$a->getId()));
		$approved = $a->getStatus() === 'approved';
		$this->safe('notify absence decision', fn () => $this->notifications->send($a->getUid(), $approved ? 'absence_approved' : 'absence_rejected', [
			'name' => $name, 'kind' => $a->getKind(), 'from' => $a->getFromDay(), 'to' => $a->getToDay(),
		], 'absence_decision', (string)$a->getId(), 'timeguardian.page.index', '#/absences'));
		if ($approved) {
			$eventUid = $this->calendar->syncAbsence($a, $name, $this->userConf->langOf($a->getUid()));
			$this->safe('webhook absence', fn () => $this->webhook->emit('absence.approved', [
				'uid' => $a->getUid(), 'name' => $name, 'kind' => $a->getKind(), 'fromDay' => $a->getFromDay(), 'toDay' => $a->getToDay(), 'decidedBy' => $decider,
			]));
		}
		return $eventUid;
	}

	public function absenceRemoved(Absence $a): void {
		$this->calendar->removeAbsence($a);
		$this->safe('dismiss absence', fn () => $this->notifications->dismissForAll($this->access->chefUids(), 'absence', (string)$a->getId()));
	}

	public function correctionRequested(Correction $c, string $day): void {
		$name = $this->access->displayName($c->getUid());
		foreach ($this->decidersFor($c->getUid()) as $chef) {
			$this->safe('notify correction request', fn () => $this->notifications->send($chef, 'correction_request', ['name' => $name, 'day' => $day], 'correction', (string)$c->getId(), 'timeguardian.page.index', '#/corrections'));
		}
	}

	public function correctionDecided(Correction $c, string $day): void {
		$this->safe('dismiss correction request', fn () => $this->notifications->dismissForAll($this->access->chefUids(), 'correction', (string)$c->getId()));
		$approved = $c->getStatus() === 'approved';
		$this->safe('notify correction decision', fn () => $this->notifications->send($c->getUid(), $approved ? 'correction_approved' : 'correction_rejected', ['day' => $day], 'correction_decision', (string)$c->getId(), 'timeguardian.page.index', '#/corrections'));
	}

	public function live(string $uid, string $kind, int $netMin, string $day): void {
		$this->safe('live warning', fn () => $this->notifications->send($uid, $kind, ['min' => $netMin], 'live', $uid . '|' . $day . '|' . $kind, 'timeguardian.page.index', '#/week'));
	}

	public function weekly(string $chefUid, array $params): void {
		$this->safe('weekly report', fn () => $this->notifications->send($chefUid, 'weekly_report', $params, 'weekly', $params['year'] . '-' . $params['kw'], 'timeguardian.page.index', '#/team'));
	}
}
