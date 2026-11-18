<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Domain;

/**
 * Punch state machine: none -> working -> (pause -> working)* -> done. After 'done' a new block may start
 * (mode simple: the gap between blocks becomes the break).
 */
final class PunchMachine {
	public const IN = 'in';
	public const OUT = 'out';
	public const PAUSE = 'pause';
	public const RESUME = 'resume';
	public const ACTIONS = ['in', 'out', 'pause', 'resume'];

	public const NONE = 'none';
	public const WORKING = 'working';
	public const ON_PAUSE = 'pause';
	public const DONE = 'done';

	/** minimum distance between two punches of one user in seconds */
	public const MIN_GAP = 120;

	public static function allowed(string $state, string $action): bool {
		return (($state === self::NONE || $state === self::DONE) && $action === self::IN)
			|| ($state === self::WORKING && ($action === self::PAUSE || $action === self::OUT))
			|| ($state === self::ON_PAUSE && $action === self::RESUME);
	}

	public static function next(string $state, string $action): string {
		if (!self::allowed($state, $action)) {
			throw new TgException('invalid_transition', [], ['state' => $state]);
		}
		return match ($action) {
			self::IN, self::RESUME => self::WORKING,
			self::PAUSE => self::ON_PAUSE,
			default => self::DONE,
		};
	}

	/**
	 * Which action does a punch mean?
	 * - explicit action: must be allowed in the state
	 * - none/done: in; working: out (mode simple) or pause (mode pause); pause: resume
	 */
	public static function decide(string $state, string $mode, ?string $action): string {
		if ($action !== null && $action !== '') {
			if (!in_array($action, self::ACTIONS, true) || !self::allowed($state, $action)) {
				throw new TgException('invalid_transition', [], ['state' => $state]);
			}
			return $action;
		}
		return match ($state) {
			self::WORKING => $mode === 'pause' ? self::PAUSE : self::OUT,
			self::ON_PAUSE => self::RESUME,
			default => self::IN,
		};
	}

	/** does this punch need a scanned code? only 'in' (and the unspecified toggle, which is decided by the caller) */
	public static function needsCode(?string $action): bool {
		return $action === null || $action === '' || $action === self::IN;
	}

	/**
	 * State from the user's most recent stamp.
	 * @param array{action:string,at:int}|null $last
	 */
	public static function resolveState(?array $last, int $now, \DateTimeZone $tz): string {
		if ($last === null) {
			return self::NONE;
		}
		return match ($last['action']) {
			self::IN, self::RESUME => self::WORKING,
			self::PAUSE => self::ON_PAUSE,
			self::OUT => Tz::day((int)$last['at'], $tz) === Tz::day($now, $tz) ? self::DONE : self::NONE,
			default => self::NONE,
		};
	}

	public static function checkInterval(?int $lastAt, int $now): void {
		if ($lastAt !== null && $now - $lastAt < self::MIN_GAP) {
			throw new TgException('too_fast');
		}
	}

	// ---- port of the reference day reducer (kinds start/pause/resume/end, minutes of day) -------------------------------

	/** legacy kind names used in the shared test vectors */
	public const KIND_TO_ACTION = ['start' => 'in', 'pause' => 'pause', 'resume' => 'resume', 'end' => 'out'];

	/**
	 * Reduces one person's punches of a day to start/end/pause/net.
	 * @param list<array{time:string,kind:string,note?:string}> $rows
	 * @return array{state:string,start:?int,end:?int,pause:int,net:int,notes:list<string>}
	 */
	public static function day(array $rows, ?int $nowMin = null): array {
		$state = self::NONE;
		$start = null;
		$end = null;
		$last = null;
		$pAt = null;
		$pause = 0;
		$work = 0;
		$notes = [];
		foreach ($rows as $r) {
			$t = Dates::parseHM($r['time'] ?? null);
			$action = self::KIND_TO_ACTION[$r['kind'] ?? ''] ?? ($r['kind'] ?? '');
			// the reference reducer never lets a finished day restart
			$ok = ($state === self::NONE && $action === self::IN)
				|| ($state === self::WORKING && ($action === self::PAUSE || $action === self::OUT))
				|| ($state === self::ON_PAUSE && $action === self::RESUME);
			if ($t === null || !$ok) {
				continue;
			}
			if (!empty($r['note'])) {
				$notes[] = $r['note'];
			}
			if ($action === self::IN) {
				$state = self::WORKING;
				$start = $t;
				$last = $t;
			} elseif ($action === self::PAUSE) {
				$work += max(0, $t - $last);
				$state = self::ON_PAUSE;
				$pAt = $t;
			} elseif ($action === self::RESUME) {
				$pause += max(0, $t - $pAt);
				$state = self::WORKING;
				$last = $t;
			} else {
				$work += max(0, $t - $last);
				$state = self::DONE;
				$end = $t;
			}
		}
		$net = $work + ($state === self::WORKING && $nowMin !== null ? max(0, $nowMin - $last) : 0);
		return ['state' => $state, 'start' => $start, 'end' => $end, 'pause' => $pause, 'net' => $net, 'notes' => $notes];
	}
}
