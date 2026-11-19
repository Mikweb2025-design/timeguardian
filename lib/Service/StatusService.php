<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Service;

use OCA\TimeGuardian\Db\EntryMapper;
use OCA\TimeGuardian\Db\StampMapper;
use OCA\TimeGuardian\Domain\Dates;
use OCA\TimeGuardian\Domain\PunchMachine;
use OCA\TimeGuardian\Domain\Report;
use OCA\TimeGuardian\Domain\Tz;
use OCP\AppFramework\Utility\ITimeFactory;

/** Current punch state of a user and the numbers shown next to it. */
class StatusService {
	public function __construct(
		private EntryMapper $entries,
		private StampMapper $stamps,
		private UserConfService $userConf,
		private ConfigService $config,
		private AccessService $access,
		private ITimeFactory $time,
	) {
	}

	/**
	 * @return array{state:string,since:?string,openEntryId:?int,pauseSince:?string,now:string}
	 */
	public function state(string $uid): array {
		$now = $this->time->getTime();
		$tz = $this->config->tz();
		$last = $this->stamps->lastOf($uid);
		$open = $this->entries->findOpen($uid);
		$state = PunchMachine::resolveState($last === null ? null : ['action' => $last->getAction(), 'at' => (int)$last->getAtTs()], $now, $tz);
		if ($open === null && ($state === PunchMachine::WORKING || $state === PunchMachine::ON_PAUSE)) {
			// the open entry was deleted: nothing is running any more
			$state = PunchMachine::NONE;
		}
		if ($open !== null && ($state === PunchMachine::NONE || $state === PunchMachine::DONE)) {
			$state = PunchMachine::WORKING;
		}
		if ($state === PunchMachine::NONE && $last !== null && $last->getAction() === PunchMachine::OUT && Tz::day((int)$last->getAtTs(), $tz) === Tz::day($now, $tz)) {
			$state = PunchMachine::DONE;
		}
		$since = null;
		$pauseSince = null;
		if ($state === PunchMachine::WORKING) {
			$since = ($last !== null && in_array($last->getAction(), [PunchMachine::IN, PunchMachine::RESUME], true)) ? (int)$last->getAtTs() : ($open === null ? null : (int)$open->getStartTs());
		} elseif ($state === PunchMachine::ON_PAUSE) {
			$pauseSince = $last === null ? null : (int)$last->getAtTs();
			$since = $open === null ? null : (int)$open->getStartTs();
		} elseif ($state === PunchMachine::DONE && $last !== null) {
			$since = (int)$last->getAtTs();
		}
		return [
			'state' => $state,
			'since' => Dates::isoOrNull($since),
			'openEntryId' => $open === null ? null : (int)$open->getId(),
			'pauseSince' => Dates::isoOrNull($pauseSince),
			'now' => Dates::iso($now),
		];
	}

	/** instant that "running" is clipped to: frozen at the start of a break */
	public function clip(array $state, int $now): int {
		if ($state['state'] === PunchMachine::ON_PAUSE && $state['pauseSince'] !== null) {
			return (int)Dates::parseIso($state['pauseSince']);
		}
		return $now;
	}

	/**
	 * @return array{name:string,mode:string,state:string,since:?string,pauseSince:?string,now:string,netMinToday:int,weekNetMin:int,targetMin:int,limitMin:int}
	 */
	public function status(string $uid): array {
		$now = $this->time->getTime();
		$tz = $this->config->tz();
		$today = Tz::day($now, $tz);
		$state = $this->state($uid);
		$clip = $this->clip($state, $now);
		$mon = Dates::monday($today);
		$rows = $this->entries->findRange($uid, Dates::add($mon, -1), Dates::add($mon, 6));
		$week = 0;
		$day = 0;
		foreach ($rows as $e) {
			$v = Report::entryView($e->toRow(), $clip, $tz, $today, true);
			if ($e->getDay() >= $mon) {
				$week += $v['netMin'];
			}
			if ($e->getDay() === $today || ($v['running'] && $e->getDay() < $today)) {
				$day += $v['netMin'];
			}
		}
		$conf = $this->userConf->get($uid);
		return [
			'name' => $this->access->displayName($uid),
			'mode' => $conf['mode'],
			'state' => $state['state'],
			'since' => $state['since'],
			'pauseSince' => $state['pauseSince'],
			'now' => $state['now'],
			'netMinToday' => $day,
			'weekNetMin' => $week,
			'targetMin' => $conf['weeklyMin'],
			'limitMin' => 2880,
		];
	}
}
