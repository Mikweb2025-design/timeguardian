<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Service;

use OCA\TimeGuardian\Db\Entry;
use OCA\TimeGuardian\Db\EntryMapper;
use OCA\TimeGuardian\Db\Stamp;
use OCA\TimeGuardian\Db\StampMapper;
use OCA\TimeGuardian\Domain\Dates;
use OCA\TimeGuardian\Domain\PunchMachine;
use OCA\TimeGuardian\Domain\Report;
use OCA\TimeGuardian\Domain\Sanitize;
use OCA\TimeGuardian\Domain\TgException;
use OCA\TimeGuardian\Domain\Tz;
use OCA\TimeGuardian\Domain\Validator;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;

/** Punch clock: QR code in, state machine, stamps (audit) and entries. */
class PunchService {
	public const DEVICE_LIMIT_PER_HOUR = 30;

	public function __construct(
		private EntryMapper $entries,
		private StampMapper $stamps,
		private StatusService $status,
		private UserConfService $userConf,
		private QrService $qr,
		private ConfigService $config,
		private AccessService $access,
		private ViolationService $violations,
		private EffectsService $effects,
		private IDBConnection $db,
		private ITimeFactory $time,
	) {
	}

	/**
	 * @param array{code?:mixed,action?:mixed,project?:mixed,note?:mixed} $in
	 * @param string $source 'scan' or 'session'
	 */
	public function punch(string $uid, array $in, string $source, ?int $deviceId, string $ip): array {
		$now = $this->time->getTime();
		$tz = $this->config->tz();
		$this->access->assertMember($uid);
		$conf = $this->userConf->get($uid);

		$actionIn = (isset($in['action']) && is_string($in['action']) && $in['action'] !== '') ? $in['action'] : null;
		if ($actionIn !== null && !in_array($actionIn, PunchMachine::ACTIONS, true)) {
			throw new TgException('invalid_transition');
		}
		$code = (isset($in['code']) && is_string($in['code'])) ? trim($in['code']) : '';
		if ($code === '' && PunchMachine::needsCode($actionIn)) {
			throw new TgException('code_required');
		}
		if ($code !== '' && !$this->qr->verify($code)) {
			throw new TgException('invalid_code');
		}
		$last = $this->stamps->lastOf($uid);
		PunchMachine::checkInterval($last === null ? null : (int)$last->getAtTs(), $now);
		if ($deviceId !== null && $this->stamps->countForDeviceSince($deviceId, $now - 3600) >= self::DEVICE_LIMIT_PER_HOUR) {
			throw new TgException('rate_limited');
		}
		$state = $this->status->state($uid);
		$action = PunchMachine::decide($state['state'], $conf['mode'], $actionIn);

		$project = null;
		if (array_key_exists('project', $in) && $in['project'] !== null && $in['project'] !== '') {
			$project = Validator::project($in['project'], $this->config->projects());
			if ($project === false) {
				throw new TgException('validation', ['project']);
			}
		}
		$note = Sanitize::textOrNull($in['note'] ?? null, 255);
		$minute = intdiv($now, 60) * 60;

		$this->db->beginTransaction();
		try {
			$entry = null;
			if ($action === PunchMachine::IN) {
				$entry = new Entry();
				$entry->setUid($uid);
				$entry->setDay(Tz::day($now, $tz));
				$entry->setStartTs($minute);
				$entry->setEndTs(null);
				$entry->setPauseMin(0);
				$entry->setProject($project);
				$entry->setNote($note);
				$entry->setSource('scan');
				$entry->setCreatedAt($now);
				$entry->setUpdatedAt($now);
				/** @var Entry $entry */
				$entry = $this->entries->insert($entry);
			} else {
				$entry = $this->entries->findOpen($uid);
				if ($entry === null) {
					throw new TgException('invalid_transition', [], ['state' => $state['state']]);
				}
				if ($action === PunchMachine::RESUME && $last !== null) {
					$entry->setPauseMin($entry->getPauseMin() + max(0, intdiv($now - (int)$last->getAtTs(), 60)));
				}
				if ($action === PunchMachine::OUT) {
					$entry->setEndTs(max($minute, (int)$entry->getStartTs() + 60));
				}
				if ($project !== null) {
					$entry->setProject($project);
				}
				if ($note !== null) {
					$entry->setNote($note);
				}
				$entry->setUpdatedAt($now);
				$this->entries->update($entry);
			}
			$s = new Stamp();
			$s->setUid($uid);
			$s->setAtTs($now);
			$s->setAction($action);
			$s->setDeviceId($deviceId);
			$s->setSource($source === 'scan' ? 'scan' : 'session');
			$s->setIpHash(hash_hmac('sha256', $ip, $this->config->qrSecret()));
			$this->stamps->insert($s);
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		$new = [];
		if ($action === PunchMachine::OUT && $entry !== null) {
			$endDay = Tz::day((int)$entry->getEndTs(), $tz);
			$r = $this->violations->refresh($uid, $entry->getDay(), max($entry->getDay(), $endDay));
			$new = $r['new'];
			$this->effects->violations($uid, $new);
			$this->effects->entrySaved($entry->toRow());
		}
		$newState = $this->status->state($uid);
		$this->effects->punched($uid, $action, $newState['state'], $entry === null ? null : (int)$entry->getId());
		$st = $this->status->status($uid);
		$lang = $this->userConf->langOf($uid);
		$today = Tz::day($now, $tz);
		return [
			'ok' => true,
			'action' => $action,
			'name' => $st['name'],
			'mode' => $st['mode'],
			'state' => $newState,
			'entry' => $entry === null ? null : Report::entryView($entry->toRow(), $this->status->clip($newState, $now), $tz, $today, true),
			'netMinToday' => $st['netMinToday'],
			'weekNetMin' => $st['weekNetMin'],
			'newViolations' => array_map(fn ($v) => Report::violationView($v, $lang), $new),
		];
	}
}
