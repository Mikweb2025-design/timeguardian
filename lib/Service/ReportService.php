<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Service;

use OCA\TimeGuardian\Db\AbsenceMapper;
use OCA\TimeGuardian\Db\CorrectionMapper;
use OCA\TimeGuardian\Db\EntryMapper;
use OCA\TimeGuardian\Domain\Balance;
use OCA\TimeGuardian\Domain\Dates;
use OCA\TimeGuardian\Domain\Report;
use OCA\TimeGuardian\Domain\TgException;
use OCA\TimeGuardian\Domain\Tz;
use OCP\AppFramework\Utility\ITimeFactory;

/** Read models: week, month, account, violations, me, team overview, person view. */
class ReportService {
	public const MAX_RANGE_DAYS = 366;

	public function __construct(
		private EntryMapper $entries,
		private AbsenceMapper $absences,
		private CorrectionMapper $corrections,
		private UserConfService $userConf,
		private ConfigService $config,
		private ViolationService $violations,
		private StatusService $status,
		private AccessService $access,
		private DeviceService $devices,
		private ITimeFactory $time,
	) {
	}

	public function today(): string {
		return Tz::day($this->time->getTime(), $this->config->tz());
	}

	/** validates a from/to pair from the request */
	public function range(mixed $from, mixed $to, int $defaultBackDays = 30): array {
		$today = $this->today();
		$to = ($to === null || $to === '') ? $today : $to;
		$from = ($from === null || $from === '') ? Dates::add($to === $today || !Dates::validDay($to) ? $today : $to, -$defaultBackDays) : $from;
		if (!Dates::validDay($from) || !Dates::validDay($to)) {
			throw new TgException('validation', ['date']);
		}
		if ($to < $from || Dates::diff($from, $to) >= self::MAX_RANGE_DAYS) {
			throw new TgException('validation', ['range']);
		}
		return [$from, $to];
	}

	/** @return list<array{id:int,kind:string,fromDay:string,toDay:string}> */
	private function approved(string $uid, string $from, string $to): array {
		$out = [];
		foreach ($this->absences->findOverlapping([$uid], $from, $to, 'approved') as $a) {
			$out[] = ['id' => (int)$a->getId(), 'kind' => $a->getKind(), 'fromDay' => $a->getFromDay(), 'toDay' => $a->getToDay()];
		}
		return $out;
	}

	/** @return list<array> day objects of one user */
	public function days(string $uid, string $from, string $to, bool $own = true, ?array $state = null): array {
		$now = $this->time->getTime();
		$tz = $this->config->tz();
		$state ??= $this->status->state($uid);
		$entries = array_map(fn ($e) => $e->toRow(), $this->entries->findRange($uid, $from, $to));
		$abs = Balance::expandAbsences($this->approved($uid, $from, $to), $from, $to);
		return Report::days($from, $to, $entries, $this->violations->range($uid, $from, $to), $abs, $this->userConf->get($uid), $this->config->state(), $tz, $this->userConf->langOf($uid), $this->today(), $this->status->clip($state, $now), $own);
	}

	/** API shape of one entry (the viewer is not the owner: not editable) */
	public function entryView(\OCA\TimeGuardian\Db\Entry $e): array {
		return Report::entryView($e->toRow(), $this->time->getTime(), $this->config->tz(), $this->today(), false);
	}

	public function week(string $uid, ?string $date, bool $own = true, ?array $state = null): array {
		$date = ($date === null || $date === '') ? $this->today() : $date;
		if (!Dates::validDay($date)) {
			throw new TgException('validation', ['date']);
		}
		$h = Report::weekHeader($date);
		$days = $this->days($uid, $h['from'], $h['to'], $own, $state);
		$conf = $this->userConf->get($uid);
		$viol = [];
		foreach ($days as $d) {
			array_push($viol, ...$d['violations']);
		}
		// WEEK48 sits on the last day with data; keep it once
		return $h + ['targetMin' => $conf['weeklyMin'], 'limitMin' => 2880] + Report::totals($days) + ['days' => $days, 'violations' => $viol];
	}

	public function month(string $uid, mixed $ym): array {
		$ym = ($ym === null || $ym === '') ? substr($this->today(), 0, 7) : $ym;
		$r = Dates::monthRange($ym);
		if ($r === null) {
			throw new TgException('validation', ['date']);
		}
		$days = $this->days($uid, $r[0], $r[1]);
		$viol = [];
		foreach ($days as $d) {
			array_push($viol, ...$d['violations']);
		}
		$entries = array_map(fn ($e) => $e->toRow(), $this->entries->findRange($uid, $r[0], $r[1]));
		return ['ym' => $ym, 'from' => $r[0], 'to' => $r[1]] + Report::totals($days) + ['days' => $days, 'violations' => $viol, 'byProject' => Report::byProject($entries, $this->time->getTime())];
	}

	public function account(string $uid): array {
		return $this->accountThrough($uid, Dates::add($this->today(), -1));
	}

	/** running balance up to and including $through (capped at yesterday) */
	public function accountThrough(string $uid, string $through): array {
		$today = $this->today();
		$through = min($through, Dates::add($today, -1));
		$floor = Dates::add($today, -$this->config->retentionDays());
		$first = $this->entries->firstDay($uid);
		$since = $first === null ? $today : max($first, $floor);
		$conf = $this->userConf->get($uid);
		$net = Report::netByDay(array_map(fn ($e) => $e->toRow(), $this->entries->findRange($uid, $since, $through)));
		$a = Balance::account($since, $through, $conf['weeklyMin'], $conf['daysMask'], $this->config->state(), $net, $this->approved($uid, $since, $through));
		return $a;
	}

	public function violationList(string $uid, string $from, string $to): array {
		$lang = $this->userConf->langOf($uid);
		return array_map(fn ($v) => Report::violationView($v, $lang), $this->violations->range($uid, $from, $to));
	}

	public function me(string $uid): array {
		$conf = $this->userConf->get($uid);
		$state = $this->status->state($uid);
		$today = $this->today();
		$week = $this->week($uid, $today, true, $state);
		$todayDay = null;
		foreach ($week['days'] as $d) {
			if ($d['day'] === $today) {
				$todayDay = $d;
			}
		}
		$account = $this->account($uid);
		$account['todaySollMin'] = $todayDay['sollMin'] ?? 0;
		$account['todayNetMin'] = $todayDay['netMin'] ?? 0;
		$isChef = $this->access->isChef($uid);
		$lang = $this->userConf->langOf($uid);
		return [
			'uid' => $uid,
			'name' => $this->access->displayName($uid),
			'isChef' => $isChef,
			'isKiosk' => $this->access->canKiosk($uid),
			'isAdmin' => $this->access->isAdmin($uid),
			'lang' => $lang,
			'timezone' => $this->config->timezoneName(),
			'today' => $today,
			'conf' => ['weeklyMin' => $conf['weeklyMin'], 'daysMask' => $conf['daysMask'], 'mode' => $conf['mode'], 'lang' => $conf['lang'], 'canSetTarget' => $isChef],
			'state' => $state,
			'todaySummary' => ['day' => $today, 'netMin' => $todayDay['netMin'] ?? 0, 'pauseMin' => $todayDay['pauseMin'] ?? 0, 'sollMin' => $todayDay['sollMin'] ?? 0, 'entries' => $todayDay['entries'] ?? []],
			'week' => $week,
			'account' => $account,
			'deviceCount' => $this->devices->count($uid),
			'projects' => $this->config->projects(),
			'features' => [
				'calendar' => $this->config->integration('calendar') || $this->config->integration('absence_calendar'),
				'deck' => $this->config->integration('deck'),
				'talk' => $this->config->integration('talk'),
				'webhook' => $this->config->webhookUrl() !== '',
				'liveWarnings' => $this->config->liveWarnings(),
			],
			'pendingDecisions' => $isChef ? $this->pendingCounts($uid) : ['absences' => 0, 'corrections' => 0],
		];
	}

	/** @return array{absences:int,corrections:int} requests the manager may decide */
	public function pendingCounts(string $chef): array {
		$uids = array_values(array_filter($this->access->memberUids(), fn ($u) => $this->access->policy()->canDecide($chef, $u)));
		return ['absences' => count($this->absences->findPending($uids)), 'corrections' => count($this->corrections->findPending($uids))];
	}

	/** manager overview of one week */
	public function teamOverview(string $chef, ?string $weekDate): array {
		$weekDate = ($weekDate === null || $weekDate === '') ? $this->today() : $weekDate;
		if (!Dates::validDay($weekDate)) {
			throw new TgException('validation', ['date']);
		}
		$h = Report::weekHeader($weekDate);
		$uids = array_values(array_filter($this->access->memberUids(), fn ($u) => $this->access->policy()->canView($chef, $u)));
		$allViol = $this->violations->rangeForUsers($uids, $h['from'], $h['to']);
		$lang = $this->userConf->langOf($chef);
		$people = [];
		$present = [];
		$top = [];
		foreach ($uids as $uid) {
			$state = $this->status->state($uid);
			$days = $this->days($uid, $h['from'], $h['to'], false, $state);
			$tot = Report::totals($days);
			$acc = $this->account($uid);
			$mine = array_values(array_filter($allViol, fn ($v) => $v['uid'] === $uid));
			$name = $this->access->displayName($uid);
			$people[] = [
				'uid' => $uid,
				'name' => $name,
				'isChef' => $this->access->isChef($uid),
				'state' => $state['state'],
				'since' => $state['since'],
				'netMin' => $tot['netMin'],
				'sollMin' => $tot['sollMin'],
				'balanceMin' => $acc['balanceMin'],
				'days' => array_map(fn ($d) => ['day' => $d['day'], 'netMin' => $d['netMin']], $days),
				'violations' => [
					'verstoss' => count(array_filter($mine, fn ($v) => $v['level'] === 'verstoss')),
					'hinweis' => count(array_filter($mine, fn ($v) => $v['level'] === 'hinweis')),
				],
			];
			if ($state['state'] === 'working') {
				$present[] = $uid;
			}
			if ($acc['balanceMin'] > 0) {
				$top[] = ['uid' => $uid, 'name' => $name, 'balanceMin' => $acc['balanceMin']];
			}
		}
		usort($top, fn ($a, $b) => $b['balanceMin'] <=> $a['balanceMin']);
		$viol = array_map(fn ($v) => Report::violationView($v, $lang) + ['name' => $this->access->displayName($v['uid'])], $allViol);
		return [
			'week' => $h,
			'people' => $people,
			'presentNow' => $present,
			'violations' => $viol,
			'topOvertime' => array_slice($top, 0, 5),
			'pending' => $this->pendingCounts($chef),
		];
	}

	/** detail view of one team member for a manager (or of oneself) */
	public function person(string $actor, string $target, string $from, string $to): array {
		$this->access->assertCanView($actor, $target);
		$own = $actor === $target;
		$state = $this->status->state($target);
		$days = $this->days($target, $from, $to, $own, $state);
		$entries = [];
		$viol = [];
		foreach ($days as $d) {
			array_push($entries, ...$d['entries']);
			array_push($viol, ...$d['violations']);
		}
		return [
			'user' => ['uid' => $target, 'name' => $this->access->displayName($target), 'state' => $state],
			'entries' => $entries,
			'days' => $days,
			'violations' => $viol,
			'account' => $this->account($target),
		];
	}
}
