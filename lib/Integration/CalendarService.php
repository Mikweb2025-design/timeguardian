<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Integration;

use OCA\TimeGuardian\AppInfo\Application;
use OCA\TimeGuardian\Db\Absence;
use OCA\TimeGuardian\Domain\Dates;
use OCA\TimeGuardian\Domain\Ics;
use OCA\TimeGuardian\Domain\Messages;
use OCA\TimeGuardian\Service\ConfigService;
use OCP\AppFramework\Utility\ITimeFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Calendar integration on top of the DAV app's CalDavBackend (always installed with Nextcloud).
 * - approved absences become all-day events in the user's personal calendar
 * - optional: working blocks go to a calendar "Working time"
 * Every method swallows its errors: the calendar is a convenience, never a reason to fail a booking.
 */
class CalendarService {
	private const BACKEND = 'OCA\\DAV\\CalDAV\\CalDavBackend';
	public const WORK_URI = 'timeguardian';

	public function __construct(
		private ContainerInterface $container,
		private ConfigService $config,
		private ITimeFactory $time,
		private LoggerInterface $logger,
	) {
	}

	private function backend(): object {
		return $this->container->get(self::BACKEND);
	}

	private static function principal(string $uid): string {
		return 'principals/users/' . $uid;
	}

	/** @return list<array> the user's own calendars */
	private function own(string $uid): array {
		$out = [];
		foreach ($this->backend()->getCalendarsForUser(self::principal($uid)) as $c) {
			if (($c['principaluri'] ?? '') === self::principal($uid)) {
				$out[] = $c;
			}
		}
		return $out;
	}

	private function personalCalendar(string $uid): ?array {
		$own = $this->own($uid);
		foreach ($own as $c) {
			if (($c['uri'] ?? '') === 'personal') {
				return $c;
			}
		}
		foreach ($own as $c) {
			if (($c['uri'] ?? '') !== self::WORK_URI && ($c['uri'] ?? '') !== 'contact_birthdays') {
				return $c;
			}
		}
		return null;
	}

	private function workCalendar(string $uid, string $lang): ?array {
		foreach ($this->own($uid) as $c) {
			if (($c['uri'] ?? '') === self::WORK_URI) {
				return $c;
			}
		}
		$this->backend()->createCalendar(self::principal($uid), self::WORK_URI, [
			'{DAV:}displayname' => Messages::misc('calendar_name', $lang),
			'{http://apple.com/ns/ical/}calendar-color' => '#2f6fed',
		]);
		foreach ($this->own($uid) as $c) {
			if (($c['uri'] ?? '') === self::WORK_URI) {
				return $c;
			}
		}
		return null;
	}

	private function put(array $calendar, string $objectUri, string $ics): void {
		$b = $this->backend();
		$id = (int)$calendar['id'];
		if ($b->getCalendarObject($id, $objectUri) === null) {
			$b->createCalendarObject($id, $objectUri, $ics);
		} else {
			$b->updateCalendarObject($id, $objectUri, $ics);
		}
	}

	private function drop(string $uid, string $objectUri): void {
		$b = $this->backend();
		foreach ($this->own($uid) as $c) {
			if ($b->getCalendarObject((int)$c['id'], $objectUri) !== null) {
				$b->deleteCalendarObject((int)$c['id'], $objectUri);
			}
		}
	}

	/** @return string|null the event UID that was written */
	public function syncAbsence(Absence $a, string $name, string $lang): ?string {
		if (!$this->config->integration('absence_calendar')) {
			return null;
		}
		try {
			$cal = $this->personalCalendar($a->getUid()) ?? $this->workCalendar($a->getUid(), $lang);
			if ($cal === null) {
				return null;
			}
			$uid = Ics::uid('abs', (int)$a->getId());
			$summary = Messages::kind($a->getKind(), $lang);
			$ics = Ics::absence($uid, $summary, $a->getFromDay(), $a->getToDay(), $this->time->getTime(), Messages::misc('auto', $lang));
			$this->put($cal, 'tg-abs-' . $a->getId() . '.ics', $ics);
			return $uid;
		} catch (\Throwable $e) {
			$this->logger->warning('TimeGuardian calendar (absence) failed: ' . $e->getMessage(), ['app' => Application::APP_ID, 'exception' => $e]);
			return null;
		}
	}

	public function removeAbsence(Absence $a): void {
		try {
			$this->drop($a->getUid(), 'tg-abs-' . $a->getId() . '.ics');
		} catch (\Throwable $e) {
			$this->logger->warning('TimeGuardian calendar (remove absence) failed: ' . $e->getMessage(), ['app' => Application::APP_ID, 'exception' => $e]);
		}
	}

	/** a finished entry as a calendar block (only when the admin switched the calendar integration on) */
	public function syncEntry(array $entryRow, string $lang): void {
		if (!$this->config->integration('calendar') || ($entryRow['endTs'] ?? null) === null) {
			return;
		}
		try {
			$uid = (string)$entryRow['uid'];
			$cal = $this->workCalendar($uid, $lang);
			if ($cal === null) {
				return;
			}
			$net = max(0, intdiv((int)$entryRow['endTs'] - (int)$entryRow['startTs'], 60) - (int)$entryRow['pauseMin']);
			$summary = Messages::misc('work_block', $lang, ['h' => Dates::fmtH($net, $lang)]);
			$desc = ($entryRow['project'] ?? '') !== '' && $entryRow['project'] !== null ? (string)$entryRow['project'] : '';
			$ics = Ics::block(Ics::uid('entry', (int)$entryRow['id']), $summary, (int)$entryRow['startTs'], (int)$entryRow['endTs'], $this->time->getTime(), $desc);
			$this->put($cal, 'tg-entry-' . $entryRow['id'] . '.ics', $ics);
		} catch (\Throwable $e) {
			$this->logger->warning('TimeGuardian calendar (entry) failed: ' . $e->getMessage(), ['app' => Application::APP_ID, 'exception' => $e]);
		}
	}

	public function removeEntry(string $uid, int $entryId): void {
		if (!$this->config->integration('calendar')) {
			return;
		}
		try {
			$this->drop($uid, 'tg-entry-' . $entryId . '.ics');
		} catch (\Throwable $e) {
			$this->logger->warning('TimeGuardian calendar (remove entry) failed: ' . $e->getMessage(), ['app' => Application::APP_ID, 'exception' => $e]);
		}
	}
}
