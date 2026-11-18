<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Service;

use OCA\TimeGuardian\Db\AbsenceMapper;
use OCA\TimeGuardian\Db\AuditMapper;
use OCA\TimeGuardian\Db\CorrectionMapper;
use OCA\TimeGuardian\Db\DeviceMapper;
use OCA\TimeGuardian\Db\EntryMapper;
use OCA\TimeGuardian\Db\StampMapper;
use OCA\TimeGuardian\Db\ViolationMapper;
use OCA\TimeGuardian\Domain\Csv;
use OCA\TimeGuardian\Domain\Dates;
use OCA\TimeGuardian\Domain\Report;
use OCA\TimeGuardian\Domain\TgException;
use OCA\TimeGuardian\Domain\Tz;
use OCP\AppFramework\Utility\ITimeFactory;

/** CSV (detail + payroll) and the GDPR JSON export. */
class ExportService {
	public function __construct(
		private ReportService $reports,
		private AccessService $access,
		private UserConfService $userConf,
		private ConfigService $config,
		private EntryMapper $entries,
		private StampMapper $stamps,
		private DeviceMapper $devices,
		private AbsenceMapper $absences,
		private CorrectionMapper $corrections,
		private AuditMapper $audit,
		private ViolationMapper $violations,
		private DeviceService $deviceService,
		private ITimeFactory $time,
	) {
	}

	private const DETAIL_HEAD = ['uid', 'name', 'date', 'weekday', 'start', 'end', 'pause_min', 'net_min', 'project', 'note', 'source'];
	private const PAYROLL_HEAD = ['uid', 'name', 'month', 'target_min', 'net_min', 'credit_min', 'delta_min', 'vacation_days', 'sick_days', 'flex_days', 'trip_days', 'holidays', 'balance_end_min'];
	private const WEEKDAYS = [
		'de' => ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'],
		'en' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
		'it' => ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'],
	];

	/** @return array{0:string,1:string} */
	private function month(mixed $ym): array {
		$ym = ($ym === null || $ym === '') ? substr(Tz::day($this->time->getTime(), $this->config->tz()), 0, 7) : $ym;
		$r = Dates::monthRange($ym);
		if ($r === null) {
			throw new TgException('validation', ['date']);
		}
		return $r;
	}

	private function detailRows(string $uid, string $from, string $to): array {
		$tz = $this->config->tz();
		$name = $this->access->displayName($uid);
		$lang = $this->userConf->langOf($uid);
		$now = $this->time->getTime();
		$rows = [];
		foreach ($this->entries->findRange($uid, $from, $to) as $e) {
			$v = Report::entryView($e->toRow(), $now, $tz, $e->getDay(), true);
			$rows[] = [$uid, $name, $e->getDay(), self::WEEKDAYS[$lang][Dates::dow($e->getDay())], $v['startHM'], $v['endHM'] ?? '', $v['pauseMin'], $v['netMin'], $v['project'] ?? '', $v['note'] ?? '', $v['source']];
		}
		return $rows;
	}

	private function payrollRow(string $uid, string $from, string $to): array {
		$days = $this->reports->days($uid, $from, $to);
		$tot = Report::totals($days);
		$cnt = ['vacation' => 0, 'sick' => 0, 'flex' => 0, 'trip' => 0];
		$hol = 0;
		foreach ($days as $d) {
			if ($d['holiday'] !== null) {
				$hol++;
			}
			if ($d['absence'] !== null && ($d['sollMin'] > 0)) {
				$cnt[$d['absence']['kind']]++;
			}
		}
		$bal = $this->reports->accountThrough($uid, $to)['balanceMin'];
		return [$uid, $this->access->displayName($uid), substr($from, 0, 7), $tot['sollMin'], $tot['netMin'], $tot['creditMin'], $tot['deltaMin'], $cnt['vacation'], $cnt['sick'], $cnt['flex'], $cnt['trip'], $hol, $bal];
	}

	private function build(array $uids, string $from, string $to, string $format): string {
		if ($format === 'payroll') {
			return Csv::build(self::PAYROLL_HEAD, array_map(fn ($u) => $this->payrollRow($u, $from, $to), $uids), true);
		}
		$rows = [];
		foreach ($uids as $u) {
			array_push($rows, ...$this->detailRows($u, $from, $to));
		}
		return Csv::build(self::DETAIL_HEAD, $rows, true);
	}

	/** @return array{filename:string,content:string} */
	public function csvOwn(string $uid, mixed $ym, string $format): array {
		[$from, $to] = $this->month($ym);
		$f = $format === 'payroll' ? 'payroll' : 'detail';
		return ['filename' => 'timeguardian-' . $f . '-' . substr($from, 0, 7) . '.csv', 'content' => $this->build([$uid], $from, $to, $f)];
	}

	/** @return array{filename:string,content:string} */
	public function csvTeam(string $chef, mixed $ym, string $format): array {
		[$from, $to] = $this->month($ym);
		$f = $format === 'payroll' ? 'payroll' : 'detail';
		$uids = array_values(array_filter($this->access->memberUids(), fn ($u) => $this->access->policy()->canView($chef, $u)));
		return ['filename' => 'timeguardian-team-' . $f . '-' . substr($from, 0, 7) . '.csv', 'content' => $this->build($uids, $from, $to, $f)];
	}

	/** everything stored about the user (GDPR art. 15 / 20); token hashes are left out */
	public function jsonOwn(string $uid): array {
		$entries = array_map(fn ($e) => $e->toRow(), $this->entries->findAllOfUser($uid));
		return [
			'exportedAt' => Dates::iso($this->time->getTime()),
			'uid' => $uid,
			'settings' => $this->userConf->get($uid),
			'entries' => $entries,
			'stamps' => array_map(fn ($s) => ['at' => Dates::iso((int)$s->getAtTs()), 'action' => $s->getAction(), 'source' => $s->getSource(), 'deviceId' => $s->getDeviceId()], $this->stamps->findAllOfUser($uid)),
			'devices' => $this->deviceService->list($uid),
			'absences' => array_map(fn ($a) => ['kind' => $a->getKind(), 'fromDay' => $a->getFromDay(), 'toDay' => $a->getToDay(), 'status' => $a->getStatus(), 'decidedBy' => $a->getDecidedBy(), 'note' => $a->getNote()], $this->absences->findAllOfUser($uid)),
			'corrections' => array_map(fn ($c) => ['entryId' => $c->getEntryId(), 'reason' => $c->getReason(), 'status' => $c->getStatus(), 'decidedBy' => $c->getDecidedBy()], $this->corrections->findOfUser($uid)),
			'violations' => array_map(fn ($v) => $v->toRow(), $this->violations->findRange($uid, '0000-01-01', '9999-12-31')),
			'audit' => array_map(fn ($a) => ['at' => Dates::iso((int)$a->getAtTs()), 'actor' => $a->getActor(), 'kind' => $a->getKind(), 'before' => json_decode((string)$a->getBeforeJson(), true), 'after' => json_decode((string)$a->getAfterJson(), true)], $this->audit->findAllOfUser($uid)),
		];
	}
}
