<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Tests\Domain;

use OCA\TimeGuardian\Domain\Messages;
use OCA\TimeGuardian\Domain\UiText;
use OCA\TimeGuardian\Tests\TestCase;

final class MessagesTest extends TestCase {
	public function testLanguageResolution(): void {
		$this->assertSame('de', Messages::lang(null));
		$this->assertSame('de', Messages::lang(''));
		$this->assertSame('de', Messages::lang('de_DE'));
		$this->assertSame('it', Messages::lang('it'));
		$this->assertSame('en', Messages::lang('en_GB'));
		$this->assertSame('en', Messages::lang('fr'));
	}

	public function testAllDictionariesHaveTheSameKeysInEveryLanguage(): void {
		foreach (['RULE', 'ERR', 'HOLIDAY', 'KIND', 'NOTIF', 'MISC'] as $dict) {
			$tables = constant(Messages::class . '::' . $dict);
			$ref = array_keys($tables['de']);
			sort($ref);
			foreach (['en', 'it'] as $l) {
				$k = array_keys($tables[$l]);
				sort($k);
				$this->assertSame($ref, $k, "$dict de vs $l");
			}
		}
		$ref = array_keys(UiText::T['de']);
		sort($ref);
		foreach (['en', 'it'] as $l) {
			$k = array_keys(UiText::T[$l]);
			sort($k);
			$this->assertSame($ref, $k, "UiText de vs $l");
		}
	}

	public function testEveryErrorCodeUsedInTheCodeHasAText(): void {
		$root = dirname(__DIR__, 2) . '/lib';
		$codes = [];
		$it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
		foreach ($it as $f) {
			if (!str_ends_with((string)$f, '.php')) {
				continue;
			}
			$src = (string)file_get_contents((string)$f);
			if (preg_match_all("/new TgException\\('([a-z_A-Z]+)'(?:, \\[([^\\]]*)\\])?/", $src, $m)) {
				foreach ($m[1] as $i => $c) {
					$codes[$c] = true;
					if (isset($m[2][$i]) && $m[2][$i] !== '') {
						foreach (explode(',', $m[2][$i]) as $field) {
							$codes[trim($field, " '")] = true;
						}
					}
				}
			}
		}
		$this->assertTrue(count($codes) > 20);
		foreach (Messages::LANGS as $l) {
			foreach (array_keys($codes) as $c) {
				$this->assertTrue(isset(Messages::ERR[$l][$c]), "$l error text for $c");
			}
		}
	}

	public function testNotificationTextsFillAllPlaceholders(): void {
		$params = ['h' => '9,5 h', 'text' => 'T', 'name' => 'Anna', 'day' => '17.11.2026', 'kind' => 'Urlaub', 'from' => 'a', 'to' => 'b', 'kw' => 47, 'verstoss' => 1, 'hinweis' => 2];
		foreach (Messages::LANGS as $l) {
			foreach (array_keys(Messages::NOTIF[$l]) as $k) {
				[$t, $m] = Messages::notif($k, $params, $l);
				$this->assertStringNotContainsString('{', $t . $m, "$l $k");
				$this->assertTrue($t !== '' && $m !== '');
			}
		}
	}

	public function testErrorFallback(): void {
		$this->assertSame(Messages::ERR['de']['server_error'], Messages::error('nope', 'de'));
		$this->assertSame('Kein Treffer', Messages::misc('Kein Treffer', 'de'));
	}
}
