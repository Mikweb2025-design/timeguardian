<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Settings;

use OCA\TimeGuardian\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;

/**
 * Settings panels are plain server rendered HTML (forms posting to the app, no JavaScript, CSP friendly).
 * A TemplateResponse whose render() returns the prepared markup.
 */
class HtmlResponse extends TemplateResponse {
	public function __construct(private string $html) {
		parent::__construct(Application::APP_ID, 'main', [], 'blank');
	}

	public function render(): string {
		return $this->html;
	}
}
