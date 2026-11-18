<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Service;

use OCA\TimeGuardian\Db\UserConf;
use OCA\TimeGuardian\Db\UserConfMapper;
use OCA\TimeGuardian\Domain\Messages;
use OCA\TimeGuardian\Domain\TgException;
use OCA\TimeGuardian\Domain\Validator;
use OCP\IUserManager;
use OCP\L10N\IFactory;

/** Per user settings: weekly target, working days, punch mode, language. Defaults come from the admin settings. */
class UserConfService {
	public function __construct(
		private UserConfMapper $mapper,
		private ConfigService $config,
		private IUserManager $users,
		private IFactory $l10n,
	) {
	}

	/** @return array{weeklyMin:int,daysMask:int,mode:string,lang:?string} */
	public function get(string $uid): array {
		$c = $this->mapper->findByUid($uid);
		return $c === null
			? ['weeklyMin' => $this->config->defaultWeeklyMin(), 'daysMask' => $this->config->defaultDaysMask(), 'mode' => 'simple', 'lang' => null]
			: ['weeklyMin' => $c->getWeeklyMin(), 'daysMask' => $c->getDaysMask(), 'mode' => $c->getMode(), 'lang' => $c->getLang()];
	}

	/**
	 * @param list<string> $uids
	 * @return array<string,array{weeklyMin:int,daysMask:int,mode:string,lang:?string}>
	 */
	public function getMany(array $uids): array {
		$rows = $this->mapper->findByUids($uids);
		$out = [];
		foreach ($uids as $uid) {
			$c = $rows[$uid] ?? null;
			$out[$uid] = $c === null
				? ['weeklyMin' => $this->config->defaultWeeklyMin(), 'daysMask' => $this->config->defaultDaysMask(), 'mode' => 'simple', 'lang' => null]
				: ['weeklyMin' => $c->getWeeklyMin(), 'daysMask' => $c->getDaysMask(), 'mode' => $c->getMode(), 'lang' => $c->getLang()];
		}
		return $out;
	}

	private function row(string $uid): UserConf {
		$c = $this->mapper->findByUid($uid);
		if ($c === null) {
			$c = new UserConf();
			$c->setUid($uid);
			$c->setWeeklyMin($this->config->defaultWeeklyMin());
			$c->setDaysMask($this->config->defaultDaysMask());
			$c->setMode('simple');
			/** @var UserConf $c */
			$c = $this->mapper->insert($c);
		}
		return $c;
	}

	/** user changes their own mode / language */
	public function updateOwn(string $uid, array $in): array {
		$bad = [];
		$c = $this->row($uid);
		if (array_key_exists('mode', $in)) {
			if (Validator::mode($in['mode'])) {
				$c->setMode($in['mode']);
			} else {
				$bad[] = 'mode';
			}
		}
		if (array_key_exists('lang', $in)) {
			if ($in['lang'] === null || $in['lang'] === '') {
				$c->setLang(null);
			} elseif (Validator::lang($in['lang'])) {
				$c->setLang($in['lang']);
			} else {
				$bad[] = 'lang';
			}
		}
		if ($bad !== []) {
			throw new TgException('validation', $bad);
		}
		$this->mapper->update($c);
		return $this->get($uid);
	}

	/** manager sets the target of somebody */
	public function setTarget(string $uid, array $in): array {
		$bad = [];
		$c = $this->row($uid);
		if (array_key_exists('weeklyMin', $in)) {
			$v = Validator::weeklyMin($in['weeklyMin']);
			$v === null ? $bad[] = 'weeklyMin' : $c->setWeeklyMin($v);
		}
		if (array_key_exists('daysMask', $in)) {
			$v = Validator::daysMask($in['daysMask']);
			$v === null ? $bad[] = 'daysMask' : $c->setDaysMask($v);
		}
		if ($bad !== []) {
			throw new TgException('validation', $bad);
		}
		$this->mapper->update($c);
		return $this->get($uid);
	}

	/** language for texts addressed to this user: own setting, else the Nextcloud language, else de */
	public function langOf(string $uid): string {
		$c = $this->get($uid)['lang'];
		if ($c !== null && $c !== '') {
			return Messages::lang($c);
		}
		$u = $this->users->get($uid);
		return Messages::lang($u === null ? null : $this->l10n->getUserLanguage($u));
	}

	/** state cache (JSON) used for the live warning dedupe */
	public function stateCache(string $uid): array {
		$c = $this->mapper->findByUid($uid);
		$j = $c === null ? null : json_decode((string)$c->getStateCache(), true);
		return is_array($j) ? $j : [];
	}

	public function saveStateCache(string $uid, array $cache): void {
		$c = $this->row($uid);
		$c->setStateCache(json_encode($cache));
		$this->mapper->update($c);
	}

	public function deleteUser(string $uid): void {
		$this->mapper->deleteByUid($uid);
	}
}
