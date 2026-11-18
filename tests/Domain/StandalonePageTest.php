<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Tests\Domain;

use OCA\TimeGuardian\Domain\Manifest;
use OCA\TimeGuardian\Domain\StandalonePage as P;
use OCA\TimeGuardian\Tests\TestCase;

final class StandalonePageTest extends TestCase {
	private function page(array $over = []): string {
		return P::html($over + [
			'lang' => 'de', 'title' => Manifest::name('de'), 'base' => '/apps/timeguardian',
			'manifest' => '/apps/timeguardian/manifest', 'icon' => '/custom_apps/timeguardian/img/icon-192.png',
			'apple' => '/custom_apps/timeguardian/img/apple-touch-icon.png', 'jsqr' => '/custom_apps/timeguardian/js/vendor/jsqr.js',
			'css' => ['/c/tg-common.css'], 'js' => ['/j/tg-boot.js', '/j/tg-core.js'], 'page' => 'scan',
			'attrs' => ['data-code' => ''], 'requesttoken' => 'tok',
		]);
	}

	private function head(string $html): string {
		$this->assertTrue(preg_match('#<head>(.*?)</head>#s', $html, $m) === 1);
		return $m[1];
	}

	public function testHeadHasExactlyOneManifestAndOurTitle(): void {
		$html = $this->page();
		$head = $this->head($html);
		$this->assertSame(1, preg_match_all('/<link[^>]+rel="manifest"/', $html), 'exactly one manifest link in the document');
		$this->assertStringContainsString('<link rel="manifest" href="/apps/timeguardian/manifest">', $head);
		$this->assertStringContainsString('<title>Zeit-Wächter</title>', $head);
		$this->assertFalse(stripos($html, 'nextcloud') !== false, 'nothing of Nextcloud in the document');
		$this->assertFalse(str_contains($html, '/core/'), 'no core files');
		$this->assertFalse(str_contains($html, 'theming'), 'no theming icon or manifest');
	}

	public function testIosMetaTags(): void {
		$head = $this->head($this->page());
		$this->assertStringContainsString('<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">', $head);
		$this->assertStringContainsString('<link rel="apple-touch-icon" sizes="180x180" href="/custom_apps/timeguardian/img/apple-touch-icon.png">', $head);
		$this->assertSame(1, preg_match_all('/rel="apple-touch-icon"/', $head), 'one touch icon');
		$this->assertStringContainsString('<meta name="apple-mobile-web-app-capable" content="yes">', $head);
		$this->assertStringContainsString('<meta name="mobile-web-app-capable" content="yes">', $head);
		$this->assertStringContainsString('<meta name="apple-mobile-web-app-title" content="Zeit-Wächter">', $head);
		$this->assertStringContainsString('<meta name="apple-mobile-web-app-status-bar-style" content="default">', $head);
		$this->assertStringContainsString('name="theme-color"', $head);
	}

	public function testTitleIsLocalized(): void {
		$this->assertStringContainsString('<title>TimeGuardian</title>', $this->page(['lang' => 'en', 'title' => Manifest::name('en')]));
		$this->assertStringContainsString('<html lang="it">', $this->page(['lang' => 'it', 'title' => Manifest::name('it')]));
		$this->assertStringContainsString('content="Guardiano ore"', $this->page(['lang' => 'it', 'title' => Manifest::name('it')]));
	}

	public function testRootElementAndScriptsInOrder(): void {
		$html = $this->page(['page' => 'pair', 'attrs' => ['data-token' => str_repeat('a', 64)]]);
		$this->assertStringContainsString('id="tg-root"', $html);
		$this->assertStringContainsString('data-page="pair"', $html);
		$this->assertStringContainsString('data-base="/apps/timeguardian"', $html);
		$this->assertStringContainsString('data-token="' . str_repeat('a', 64) . '"', $html);
		$this->assertStringContainsString('data-requesttoken="tok"', $html);
		$this->assertStringContainsString('data-jsqr="/custom_apps/timeguardian/js/vendor/jsqr.js"', $html);
		$this->assertTrue(strpos($html, 'tg-boot.js') < strpos($html, 'tg-core.js'), 'boot script first');
		$this->assertFalse(preg_match('#<script(?![^>]*\ssrc=)#', $html) === 1, 'no inline script');
		$this->assertFalse(str_contains($html, ' nonce='), 'no nonce needed');
	}

	public function testEscaping(): void {
		$html = $this->page(['attrs' => ['data-code' => '"><script>alert(1)</script>'], 'title' => '<b>x</b>']);
		$this->assertFalse(str_contains($html, '<script>alert'), 'attribute value escaped');
		$this->assertFalse(str_contains($html, '<b>x</b>'), 'title escaped');
		$this->assertStringContainsString('&quot;&gt;&lt;script&gt;', $html);
	}

	public function testCspAllowsOnlyOwnFiles(): void {
		$csp = P::csp();
		foreach (["default-src 'none'", "script-src 'self'", "style-src 'self' 'unsafe-inline'", "img-src 'self' data: blob:", "connect-src 'self'", "media-src 'self' blob:", "manifest-src 'self'", "font-src 'self'", "base-uri 'none'", "form-action 'self'", "frame-ancestors 'none'"] as $d) {
			$this->assertTrue(in_array($d, explode('; ', $csp), true), $d);
		}
		$this->assertFalse(str_contains($csp, 'strict-dynamic'), 'no nonce duty for our own files');
		$this->assertFalse(str_contains($csp, 'nonce'));
		$this->assertFalse(str_contains($csp, 'unsafe-eval'));
		$this->assertFalse(str_contains($csp, 'http'), 'no foreign hosts');
	}

	public function testCameraIsAllowedForTheScanner(): void {
		$this->assertStringContainsString("camera 'self'", P::featurePolicy());
		$this->assertFalse(str_contains(P::featurePolicy(), "camera 'none'"));
		$this->assertStringContainsString('camera=(self)', P::permissionsPolicy());
		$this->assertStringContainsString('microphone=()', P::permissionsPolicy());
	}

	public function testManifestPointsAtGoUnderTheScope(): void {
		$icons = ['192' => '/i/192.png', '512' => '/i/512.png', 'maskable' => '/i/m.png', 'svg' => '/i/a.svg'];
		foreach (['/apps/timeguardian/', '/index.php/apps/timeguardian/', '/nc/index.php/apps/timeguardian/'] as $base) {
			$m = Manifest::build($base, $icons, 'de');
			$this->assertSame($base . 'go', $m['start_url']);
			$this->assertSame($base, $m['scope']);
			$this->assertSame($base, $m['id']);
			$this->assertSame('standalone', $m['display']);
			$this->assertSame('portrait', $m['orientation']);
			$this->assertSame('Zeit-Wächter', $m['name']);
			$this->assertSame('Zeit-Wächter', $m['short_name']);
		}
	}

	public function testPageControllerManifestIsPublicAndUncached(): void {
		$src = (string)file_get_contents(dirname(__DIR__, 2) . '/lib/Controller/PageController.php');
		$this->assertTrue(preg_match('/#\[PublicPage\]\s+#\[NoCSRFRequired\]\s+public function manifest\(/', $src) === 1);
		$this->assertStringContainsString("'Cache-Control', 'no-cache'", $src);
		$this->assertStringContainsString('application/manifest+json', $src);
	}
}
