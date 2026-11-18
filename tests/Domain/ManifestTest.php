<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Tests\Domain;

use OCA\TimeGuardian\Domain\Manifest;
use OCA\TimeGuardian\Tests\TestCase;

final class ManifestTest extends TestCase {
	private const ICONS = ['192' => '/i/192.png', '512' => '/i/512.png', 'maskable' => '/i/m.png', 'svg' => '/i/app.svg'];

	public function testInstallableFields(): void {
		$m = Manifest::build('/apps/timeguardian/', self::ICONS, 'de');
		$this->assertSame('/apps/timeguardian/', $m['id']);
		$this->assertSame('/apps/timeguardian/go', $m['start_url'], 'start page needs no Nextcloud login');
		$this->assertSame('/apps/timeguardian/', $m['scope']);
		$this->assertTrue(str_starts_with($m['start_url'], $m['scope']));
		$this->assertSame('standalone', $m['display']);
		$this->assertSame('#00679e', $m['theme_color']);
		$this->assertSame($m['name'], $m['short_name']);
	}

	public function testIconsCover192And512AndMaskable(): void {
		$m = Manifest::build('/apps/timeguardian/', self::ICONS, 'en');
		$by = [];
		foreach ($m['icons'] as $i) {
			$by[$i['sizes'] . ' ' . $i['purpose']] = $i['src'];
		}
		$this->assertSame('/i/192.png', $by['192x192 any']);
		$this->assertSame('/i/512.png', $by['512x512 any']);
		$this->assertSame('/i/m.png', $by['512x512 maskable']);
		foreach ($m['icons'] as $i) {
			$this->assertFalse($i['purpose'] === 'any maskable', 'any and maskable are separate icons');
		}
	}

	public function testNamePerLanguage(): void {
		$this->assertSame('Zeit-Wächter', Manifest::build('/a/', self::ICONS, 'de')['name']);
		$this->assertSame('TimeGuardian', Manifest::build('/a/', self::ICONS, 'en')['name']);
		$this->assertSame('Guardiano ore', Manifest::build('/a/', self::ICONS, 'it')['name']);
		$this->assertSame('TimeGuardian', Manifest::build('/a/', self::ICONS, 'fr')['name'], 'unknown language falls back to English');
		$this->assertSame('it', Manifest::build('/a/', self::ICONS, 'it-IT')['lang']);
	}

	public function testNamesMatchInfoXmlAndL10n(): void {
		$root = dirname(__DIR__, 2);
		$xml = (string)file_get_contents($root . '/appinfo/info.xml');
		$this->assertStringContainsString('<name lang="de">' . Manifest::NAME['de'] . '</name>', $xml);
		$this->assertStringContainsString('<name lang="it">' . Manifest::NAME['it'] . '</name>', $xml);
		$this->assertStringContainsString('<name>' . Manifest::NAME['en'] . '</name>', $xml);
		foreach (['de', 'it'] as $l) {
			$j = json_decode((string)file_get_contents($root . "/l10n/$l.json"), true, 512, JSON_THROW_ON_ERROR);
			$this->assertSame(Manifest::NAME[$l], $j['translations']['TimeGuardian'], "menu name $l");
		}
	}
}
