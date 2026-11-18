<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Settings;

use OCA\TimeGuardian\AppInfo\Application;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class Section implements IIconSection {
	public function __construct(private IL10N $l, private IURLGenerator $urls) {
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l->t('TimeGuardian');
	}

	public function getPriority(): int {
		return 80;
	}

	public function getIcon(): string {
		return $this->urls->imagePath(Application::APP_ID, 'app.svg');
	}
}
