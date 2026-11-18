<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Notification;

use OCA\TimeGuardian\AppInfo\Application;
use OCA\TimeGuardian\Domain\Dates;
use OCA\TimeGuardian\Domain\Messages;
use OCA\TimeGuardian\Domain\Rules;
use OCP\IURLGenerator;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

/** Renders stored notification parameters into the reader's language. */
class Notifier implements INotifier {
	public function __construct(private IURLGenerator $urls) {
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return 'TimeGuardian';
	}

	private static function day(string $iso, string $lang): string {
		if (!Dates::validDay($iso)) {
			return $iso;
		}
		return $lang === 'en' ? $iso : substr($iso, 8, 2) . '.' . substr($iso, 5, 2) . '.' . substr($iso, 0, 4);
	}

	/** @param array<string,string> $p */
	public static function display(array $p, string $lang): array {
		$out = $p;
		$min = (int)($p['min'] ?? 0);
		$out['h'] = Dates::fmtH($min, $lang);
		if (isset($p['rule'])) {
			$out['text'] = Rules::text(['rule' => $p['rule'], 'value' => (int)($p['value'] ?? 0)], $lang);
		}
		if (isset($p['kind'])) {
			$out['kind'] = Messages::kind($p['kind'], $lang);
		}
		foreach (['day', 'from', 'to'] as $k) {
			if (isset($p[$k])) {
				$out[$k] = self::day($p[$k], $lang);
			}
		}
		return $out;
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new UnknownNotificationException();
		}
		$subject = $notification->getSubject();
		$lang = Messages::lang($languageCode);
		if (!isset(Messages::NOTIF[$lang][$subject])) {
			throw new UnknownNotificationException();
		}
		[$title, $msg] = Messages::notif($subject, self::display($notification->getSubjectParameters(), $lang), $lang);
		$notification->setParsedSubject($title);
		$notification->setParsedMessage($msg);
		$notification->setIcon($this->urls->getAbsoluteURL($this->urls->imagePath(Application::APP_ID, 'app.svg')));
		return $notification;
	}
}
