<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Domain;

/**
 * The public pages (/go, /s/{code}, /pair/{token}) as a complete HTML document with its own <head>, without the
 * Nextcloud layout. iOS Safari reads manifest, icon and title for "Add to Home Screen" only from the HTML that was
 * delivered - a manifest link swapped in by JavaScript afterwards is ignored, and Nextcloud's own head would win.
 * Pure: every URL is passed in.
 */
final class StandalonePage {
	/** Content-Security-Policy of these pages: only own files, no inline script, camera is a permission, not a source. */
	public static function csp(): string {
		return implode('; ', [
			"default-src 'none'",
			"script-src 'self'",
			"style-src 'self' 'unsafe-inline'",
			"img-src 'self' data: blob:",
			"connect-src 'self'",
			"media-src 'self' blob:",
			"manifest-src 'self'",
			"font-src 'self'",
			"worker-src 'self'",
			"object-src 'none'",
			"base-uri 'none'",
			"form-action 'self'",
			"frame-ancestors 'none'",
		]);
	}

	/** Old name of the header, still sent by Nextcloud itself ("camera 'none'" by default): the scanner needs the camera. */
	public static function featurePolicy(): string {
		return "camera 'self'; autoplay 'self'; fullscreen 'none'; geolocation 'none'; microphone 'none'; payment 'none'";
	}

	public static function permissionsPolicy(): string {
		return 'camera=(self), microphone=(), geolocation=(), payment=(), fullscreen=()';
	}

	public static function e(string $s): string {
		return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	/**
	 * @param array{lang:string,title:string,base:string,manifest:string,icon:string,apple:string,jsqr:string,css:list<string>,js:list<string>,page:string,attrs?:array<string,string>,requesttoken?:string} $o
	 *        base: path of the app without trailing slash, css/js: URLs in load order (js starts with tg-boot)
	 */
	public static function html(array $o): string {
		$e = [self::class, 'e'];
		$title = $e($o['title']);
		$h = [];
		$h[] = '<!DOCTYPE html>';
		$h[] = '<html lang="' . $e($o['lang']) . '">';
		$h[] = '<head>';
		$h[] = '<meta charset="utf-8">';
		$h[] = '<title>' . $title . '</title>';
		$h[] = '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">';
		$h[] = '<meta name="color-scheme" content="light dark">';
		$h[] = '<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">';
		$h[] = '<meta name="theme-color" content="#171a1d" media="(prefers-color-scheme: dark)">';
		$h[] = '<meta name="apple-mobile-web-app-capable" content="yes">';
		$h[] = '<meta name="mobile-web-app-capable" content="yes">';
		$h[] = '<meta name="apple-mobile-web-app-title" content="' . $title . '">';
		$h[] = '<meta name="apple-mobile-web-app-status-bar-style" content="default">';
		$h[] = '<meta name="format-detection" content="telephone=no">';
		$h[] = '<meta name="referrer" content="same-origin">';
		$h[] = '<link rel="manifest" href="' . $e($o['manifest']) . '">';
		$h[] = '<link rel="apple-touch-icon" sizes="180x180" href="' . $e($o['apple']) . '">';
		$h[] = '<link rel="icon" type="image/png" sizes="192x192" href="' . $e($o['icon']) . '">';
		foreach ($o['css'] as $css) {
			$h[] = '<link rel="stylesheet" href="' . $e($css) . '">';
		}
		$h[] = '</head>';
		$h[] = '<body class="tg-standalone">';
		$attrs = [
			'id' => 'tg-root',
			'data-page' => $o['page'],
			'data-base' => $o['base'],
			'data-icon' => $o['icon'],
			'data-apple' => $o['apple'],
			'data-manifest' => $o['manifest'],
			'data-jsqr' => $o['jsqr'],
			'data-requesttoken' => $o['requesttoken'] ?? '',
		] + ($o['attrs'] ?? []);
		$a = [];
		foreach ($attrs as $k => $v) {
			$a[] = $k . '="' . $e((string)$v) . '"';
		}
		$h[] = '<div ' . implode(' ', $a) . '>';
		$h[] = '<noscript><p>' . $title . ' needs JavaScript.</p></noscript>';
		$h[] = '</div>';
		foreach ($o['js'] as $js) {
			$h[] = '<script src="' . $e($js) . '"></script>';
		}
		$h[] = '</body>';
		$h[] = '</html>';
		return implode("\n", $h) . "\n";
	}

	/** @return list<string> file names (without extension) in load order */
	public static function scripts(): array {
		return ['tg-boot', 'tg-core', 'tg-i18n', 'tg-api', 'tg-ui', 'tg-app', 'tg-install', 'tg-charts', 'tg-scanner', 'tg-punch'];
	}

	/** @return list<string> */
	public static function styles(): array {
		return ['tg-common', 'tg-standalone', 'tg-punch'];
	}
}
