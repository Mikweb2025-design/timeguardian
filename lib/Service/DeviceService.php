<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Service;

use OCA\TimeGuardian\Db\Device;
use OCA\TimeGuardian\Db\DeviceMapper;
use OCA\TimeGuardian\Db\Pair;
use OCA\TimeGuardian\Db\PairFailMapper;
use OCA\TimeGuardian\Db\PairMapper;
use OCA\TimeGuardian\Domain\Dates;
use OCA\TimeGuardian\Domain\PairCode;
use OCA\TimeGuardian\Domain\TgException;
use OCA\TimeGuardian\Domain\Tokens;
use OCA\TimeGuardian\Domain\Validator;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IURLGenerator;

/** QR login: one-time pairing tokens and long lived device tokens (only SHA-256 hashes are stored). */
class DeviceService {
	public const MAX_ACTIVE = 10;

	public function __construct(
		private DeviceMapper $devices,
		private PairMapper $pairs,
		private AccessService $access,
		private AuditService $audit,
		private ITimeFactory $time,
		private IURLGenerator $urls,
		private PairFailMapper $fails,
		private IConfig $config,
	) {
	}

	/** @return array{token:string,code:string,url:string,qrText:string,expires:string,expiresInSec:int} */
	public function createPairing(string $uid): array {
		$now = $this->time->getTime();
		$this->pairs->deleteExpired($now);
		$this->fails->purge(PairCode::windowStart($now) - 3600);
		if ($this->devices->countActive($uid, $now) >= self::MAX_ACTIVE) {
			throw new TgException('rate_limited');
		}
		$t = Tokens::generate();
		$p = new Pair();
		$p->setUid($uid);
		$p->setTokenHash($t['hash']);
		$code = PairCode::generate();
		for ($i = 0; $i < 5 && $this->pairs->activeCodeExists($this->codeHash($code), $now); $i++) {
			$code = PairCode::generate();
		}
		$p->setCodeHash($this->codeHash($code));
		$p->setExpiresAt(Tokens::newPairExpiry($now));
		$p->setUsed(0);
		$p->setCreatedAt($now);
		$this->pairs->insert($p);
		$url = $this->urls->getAbsoluteURL($this->urls->linkToRoute('timeguardian.page.pair', ['token' => $t['token']]));
		return ['token' => $t['token'], 'code' => PairCode::format($code), 'url' => $url, 'qrText' => $url, 'expires' => Dates::iso($p->getExpiresAt()), 'expiresInSec' => Tokens::PAIR_TTL];
	}

	private function codeHash(string $code): string {
		return PairCode::hash($code, (string)$this->config->getSystemValue('secret', ''));
	}

	/**
	 * Exchanges a pairing token (64 hex) OR the short code (ABCD-EFGH) for a device token. The code path has a strict
	 * rate limit: PairCode::MAX_FAILS wrong attempts per IP and hour, then rate_limited until the window has passed.
	 * @return array{deviceToken:string,name:string,expiresAt:string}
	 */
	public function redeem(string $token, string $label, string $code = '', string $ip = ''): array {
		$now = $this->time->getTime();
		if ($token !== '' || $code === '') {
			if (!Tokens::wellFormed($token)) {
				throw new TgException('token_invalid');
			}
			$pair = $this->pairs->findByHash(Tokens::hash($token));
			if ($pair === null || !Tokens::matches($token, $pair->getTokenHash())) {
				throw new TgException('token_invalid');
			}
		} else {
			$pair = $this->findByCode($code, $ip, $now);
		}
		$st = Tokens::pairStatus(['used' => $pair->getUsed(), 'expires_at' => $pair->getExpiresAt()], $now);
		if ($st === 'expired') {
			throw new TgException('token_expired');
		}
		if ($st === 'used' || !$this->pairs->markUsed((int)$pair->getId())) {
			throw new TgException('token_used');
		}
		$uid = $pair->getUid();
		if (!$this->access->userExists($uid)) {
			throw new TgException('token_invalid');
		}
		$this->access->assertMember($uid);
		$dev = Tokens::generate();
		$d = new Device();
		$d->setUid($uid);
		$d->setTokenHash($dev['hash']);
		$d->setLabel(Validator::label($label));
		$d->setCreatedAt($now);
		$d->setLastUsed($now);
		$d->setExpiresAt(Tokens::newDeviceExpiry($now));
		$d->setRevoked(0);
		/** @var Device $d */
		$d = $this->devices->insert($d);
		$this->audit->log($uid, $uid, 'device.pair', 'device', (int)$d->getId(), null, ['label' => $d->getLabel()]);
		return ['deviceToken' => $dev['token'], 'name' => $this->access->displayName($uid), 'expiresAt' => Dates::iso($d->getExpiresAt())];
	}

	private function findByCode(string $code, string $ip, int $now): Pair {
		$ipHash = hash('sha256', 'tg-pairfail|' . $ip);
		if (PairCode::blocked($this->fails->countSince($ipHash, PairCode::windowStart($now)))) {
			throw new TgException('rate_limited');
		}
		$pair = PairCode::wellFormed($code) ? $this->pairs->findByCodeHash($this->codeHash($code)) : null;
		$verdict = PairCode::evaluate($pair === null ? null : ['used' => $pair->getUsed(), 'expires_at' => (int)$pair->getExpiresAt()], $now);
		if ($pair === null || $verdict !== 'ok') {
			// every failure counts: wrong, expired and already used codes look the same to a guesser
			$this->fails->add($ipHash, $now);
			throw new TgException(['expired' => 'token_expired', 'used' => 'token_used'][$verdict] ?? 'token_invalid');
		}
		return $pair;
	}

	/**
	 * Validates the X-TG-Device header value.
	 * @throws TgException unauthorized
	 */
	public function authenticate(string $token): Device {
		if (!Tokens::wellFormed($token)) {
			throw new TgException('unauthorized');
		}
		$hash = Tokens::hash($token);
		$d = $this->devices->findByHash($hash);
		if ($d === null || !hash_equals($d->getTokenHash(), $hash)) {
			throw new TgException('unauthorized');
		}
		$now = $this->time->getTime();
		if (Tokens::deviceStatus(['revoked' => $d->getRevoked(), 'expires_at' => $d->getExpiresAt()], $now) !== 'ok' || !$this->access->userExists($d->getUid())) {
			throw new TgException('unauthorized');
		}
		$touch = Tokens::touch(['last_used' => $d->getLastUsed()], $now);
		if ($touch !== null) {
			$d->setLastUsed($touch['last_used']);
			$d->setExpiresAt($touch['expires_at']);
			$this->devices->update($d);
		}
		return $d;
	}

	/** @return list<array> */
	public function list(string $uid): array {
		$now = $this->time->getTime();
		$out = [];
		foreach ($this->devices->findOfUserAll($uid) as $d) {
			if ($d->getExpiresAt() < $now - 30 * 86400) {
				continue;
			}
			$out[] = $this->view($d);
		}
		return $out;
	}

	public function view(Device $d): array {
		return [
			'id' => (int)$d->getId(),
			'label' => $d->getLabel(),
			'createdAt' => Dates::iso($d->getCreatedAt()),
			'lastUsed' => Dates::iso($d->getLastUsed()),
			'expiresAt' => Dates::iso($d->getExpiresAt()),
			'revoked' => $d->isRevoked(),
		];
	}

	public function revoke(string $uid, int $id): void {
		$d = $this->devices->findOfUser($uid, $id);
		if ($d === null) {
			throw new TgException('not_found');
		}
		$d->setRevoked(1);
		$this->devices->update($d);
		$this->audit->log($uid, $uid, 'device.revoke', 'device', $id, ['label' => $d->getLabel()], null);
	}

	public function count(string $uid): int {
		return $this->devices->countActive($uid, $this->time->getTime());
	}
}
