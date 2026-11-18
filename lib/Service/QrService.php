<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Service;

use OCA\TimeGuardian\Domain\QrSigner;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IURLGenerator;

/** Rotating kiosk code (60 s windows, HMAC with the instance secret). */
class QrService {
	public function __construct(
		private ConfigService $config,
		private ITimeFactory $time,
		private IURLGenerator $urls,
	) {
	}

	/** @return array{url:string,qrText:string,window:int,expiresInSec:int,windowSec:int} */
	public function current(): array {
		$now = $this->time->getTime();
		$code = QrSigner::code($this->config->qrSecret(), $now);
		$url = $this->urls->getAbsoluteURL($this->urls->linkToRoute('timeguardian.page.scan', ['code' => $code]));
		return [
			'url' => $url,
			'qrText' => $url,
			'window' => QrSigner::window($now),
			'expiresInSec' => QrSigner::expiresIn($now),
			'windowSec' => QrSigner::WINDOW_SEC,
		];
	}

	public function verify(string $code): bool {
		return QrSigner::verify($this->config->qrSecret(), $code, $this->time->getTime());
	}
}
