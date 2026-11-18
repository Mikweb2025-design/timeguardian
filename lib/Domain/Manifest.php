<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Domain;

/** The web app manifest (installable PWA). Pure: URLs are passed in. */
final class Manifest {
	public const NAME = ['de' => 'Zeit-Wächter', 'en' => 'TimeGuardian', 'it' => 'Guardiano ore'];
	public const DESCRIPTION = [
		'de' => 'Arbeitszeit per QR-Code stempeln',
		'en' => 'Clock in and out by QR code',
		'it' => 'Timbra l’orario di lavoro con il codice QR',
	];

	public static function name(string $lang): string {
		return self::NAME[Messages::lang($lang)];
	}

	/**
	 * @param string $base path of the app incl. trailing slash, e.g. /apps/timeguardian/
	 * @param array{192:string,512:string,maskable:string,svg:string} $icons absolute paths of the icon files
	 */
	public static function build(string $base, array $icons, string $lang): array {
		$lang = Messages::lang($lang);
		$name = self::NAME[$lang];
		return [
			'id' => $base,
			'name' => $name,
			'short_name' => $name,
			'description' => self::DESCRIPTION[$lang],
			'lang' => $lang,
			'start_url' => $base . 'go',
			'scope' => $base,
			'display' => 'standalone',
			'orientation' => 'portrait',
			'background_color' => '#ffffff',
			'theme_color' => '#00679e',
			'categories' => ['business', 'productivity'],
			'prefer_related_applications' => false,
			'icons' => [
				['src' => $icons['192'], 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
				['src' => $icons['512'], 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
				['src' => $icons['maskable'], 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
				['src' => $icons['svg'], 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
			],
		];
	}
}
