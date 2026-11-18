<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Settings;

/** Tiny HTML helpers for the settings forms. Everything user controlled goes through esc(). */
final class Form {
	public static function esc(mixed $s): string {
		return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	public static function row(string $label, string $control, string $for = '', string $hint = ''): string {
		return '<p class="tg-adm-row"><label' . ($for !== '' ? ' for="' . self::esc($for) . '"' : '') . '><strong>' . self::esc($label) . '</strong></label><br>' . $control
			. ($hint !== '' ? '<span class="tg-adm-hint">' . self::esc($hint) . '</span>' : '') . '</p>';
	}

	/** a titled block of the settings page with an explanation under the heading */
	public static function section(string $title, string $intro, string $body): string {
		return '<fieldset class="tg-adm-sec"><legend>' . self::esc($title) . '</legend>'
			. ($intro !== '' ? '<p class="tg-adm-intro">' . self::esc($intro) . '</p>' : '') . $body . '</fieldset>';
	}

	public static function text(string $name, string $value, string $type = 'text', string $extra = ''): string {
		return '<input id="tg-' . self::esc($name) . '" name="' . self::esc($name) . '" type="' . self::esc($type) . '" value="' . self::esc($value) . '" ' . $extra . '>';
	}

	public static function check(string $name, bool $on, string $label, string $hint = ''): string {
		return '<p class="tg-adm-row"><input class="checkbox" id="tg-' . self::esc($name) . '" type="checkbox" name="' . self::esc($name) . '" value="1"' . ($on ? ' checked' : '') . '>'
			. '<label for="tg-' . self::esc($name) . '">' . self::esc($label) . '</label>'
			. ($hint !== '' ? '<span class="tg-adm-hint">' . self::esc($hint) . '</span>' : '') . '</p>';
	}

	/** @param array<string,string> $options value => label */
	public static function select(string $name, array $options, string $current): string {
		$h = '<select id="tg-' . self::esc($name) . '" name="' . self::esc($name) . '">';
		foreach ($options as $v => $l) {
			$h .= '<option value="' . self::esc($v) . '"' . ((string)$v === $current ? ' selected' : '') . '>' . self::esc($l) . '</option>';
		}
		return $h . '</select>';
	}

	/** the CSRF request token for server rendered forms */
	public static function token(): string {
		if (method_exists(\OCP\Util::class, 'callRegister')) {
			return (string)\OCP\Util::callRegister();
		}
		return (string)\OC::$server->get(\OC\Security\CSRF\CsrfTokenManager::class)->getToken()->getEncryptedValue();
	}
}
