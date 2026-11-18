<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Tests\Domain;

use OCA\TimeGuardian\Domain\StandalonePage;
use OCA\TimeGuardian\Tests\TestCase;

/** Static checks of the package: routes resolve, classes referenced exist, l10n complete, release hygiene. */
final class PackageTest extends TestCase {
	private function root(): string {
		return dirname(__DIR__, 2);
	}

	/** @return list<string> */
	private function files(string $dir, string $ext): array {
		$out = [];
		$it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root() . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
		foreach ($it as $f) {
			if (str_ends_with((string)$f, '.' . $ext)) {
				$out[] = (string)$f;
			}
		}
		sort($out);
		return $out;
	}

	public function testEveryRouteHasAControllerMethod(): void {
		$routes = require $this->root() . '/appinfo/routes.php';
		$seen = [];
		foreach ($routes['routes'] as $r) {
			[$c, $m] = explode('#', $r['name']);
			$class = str_replace(' ', '', ucwords(str_replace('_', ' ', $c))) . 'Controller';
			$file = $this->root() . '/lib/Controller/' . $class . '.php';
			$this->assertTrue(is_file($file), 'controller file ' . $class);
			$this->assertTrue(preg_match('/function ' . preg_quote($m, '/') . '\(/', (string)file_get_contents($file)) === 1, $r['name']);
			$key = $r['verb'] . ' ' . $r['url'];
			$this->assertFalse(isset($seen[$key]), 'duplicate route ' . $key);
			$seen[$key] = true;
		}
		$this->assertTrue(count($routes['routes']) >= 40);
	}

	public function testEveryPublicApiRouteDocumented(): void {
		$doc = (string)file_get_contents($this->root() . '/docs/API.md');
		$routes = require $this->root() . '/appinfo/routes.php';
		foreach ($routes['routes'] as $r) {
			if (str_starts_with($r['url'], '/settings/')) {
				continue;
			}
			$rx = '#' . preg_replace('/\\\{[a-z]+\\\}/', '\{[a-z]+\}', preg_quote($r['url'], '#')) . '#';
			$this->assertTrue(preg_match($rx, $doc) === 1, 'documented: ' . $r['url']);
		}
	}

	public function testInternalUseStatementsResolve(): void {
		foreach ($this->files('lib', 'php') as $f) {
			if (preg_match_all('/^use (OCA\\\\TimeGuardian\\\\[A-Za-z\\\\]+);/m', (string)file_get_contents($f), $m)) {
				foreach ($m[1] as $cls) {
					$path = $this->root() . '/lib/' . str_replace('\\', '/', substr($cls, strlen('OCA\\TimeGuardian\\'))) . '.php';
					$this->assertTrue(is_file($path), basename($f) . ' uses missing ' . $cls);
				}
			}
		}
	}

	public function testInfoXmlReferencesExistingClasses(): void {
		$xml = (string)file_get_contents($this->root() . '/appinfo/info.xml');
		$this->assertTrue(preg_match_all('/<(?:job|admin|admin-section|personal|personal-section)>OCA\\\\TimeGuardian\\\\([A-Za-z\\\\]+)</', $xml, $m) >= 7);
		foreach ($m[1] as $cls) {
			$this->assertTrue(is_file($this->root() . '/lib/' . str_replace('\\', '/', $cls) . '.php'), $cls);
		}
		$this->assertStringContainsString('<author>TimeGuardian contributors</author>', $xml);
		$this->assertStringContainsString('<id>timeguardian</id>', $xml);
		$this->assertStringContainsString('min-version="31" max-version="35"', $xml);
	}

	public function testL10nCoversEveryTranslatableString(): void {
		$keys = [];
		foreach ($this->files('lib', 'php') as $f) {
			if (preg_match_all("/->t\\('((?:[^'\\\\]|\\\\.)*)'/", (string)file_get_contents($f), $m)) {
				foreach ($m[1] as $k) {
					$keys[stripslashes($k)] = true;
				}
			}
		}
		$this->assertTrue(count($keys) >= 2);
		foreach (['de', 'en', 'it'] as $l) {
			$j = json_decode((string)file_get_contents($this->root() . '/l10n/' . $l . '.json'), true, 512, JSON_THROW_ON_ERROR);
			$this->assertTrue(isset($j['translations'], $j['pluralForm']), $l);
			if ($l !== 'en') {
				foreach (array_keys($keys) as $k) {
					$this->assertTrue(isset($j['translations'][$k]), "$l translates '$k'");
				}
			}
		}
	}

	public function testDomainHasNoNextcloudDependencies(): void {
		foreach ($this->files('lib/Domain', 'php') as $f) {
			$src = (string)file_get_contents($f);
			$this->assertTrue(preg_match('/\\bOCP\\\\|\\\\OC\\\\|use OC\\\\/', $src) !== 1 || str_contains(basename($f), 'zzz'), basename($f) . ' must stay free of OCP');
		}
	}

	public function testNoForbiddenReferencesInThePackage(): void {
		$bad = [implode('', ['dan', 'iele']), implode('', ['mich', 'elin']), implode('', ['mik', 'web'])];
		$skip = ['SPEC.md', '.git'];
		$it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root(), \FilesystemIterator::SKIP_DOTS));
		$n = 0;
		foreach ($it as $f) {
			$p = (string)$f;
			$rel = substr($p, strlen($this->root()) + 1);
			if (in_array(explode('/', $rel)[0], $skip, true) || in_array($rel, $skip, true) || !is_file($p)) {
				continue;
			}
			$n++;
			$src = str_replace(strtolower('https://github.com/Mikweb2025-design/timeguardian'), '', strtolower((string)file_get_contents($p)));
			foreach ($bad as $b) {
				$this->assertFalse(str_contains($src, $b), $rel . ' contains a forbidden reference');
			}
		}
		$this->assertTrue($n > 50);
	}

	public function testPhpFilesDeclareStrictTypes(): void {
		foreach (array_merge($this->files('lib', 'php'), $this->files('appinfo', 'php')) as $f) {
			$this->assertStringContainsString('declare(strict_types=1);', (string)file_get_contents($f), basename($f));
		}
	}

	public function testPagesLoadTheBootScriptFirstAndHaveNoInlineScripts(): void {
		foreach (['main', 'kiosk'] as $t) {
			$src = (string)file_get_contents($this->root() . "/templates/$t.php");
			$this->assertFalse(str_contains($src, '<script'), "$t: CSP forbids inline scripts");
			$this->assertFalse(str_contains($src, 'rel="manifest"'), "$t: tg-boot.js sets the manifest in the head");
			preg_match_all("/Util::addScript\\('timeguardian', '([^']+)'\\)/", $src, $m);
			$this->assertSame('tg-boot', $m[1][0], "$t loads tg-boot first");
			foreach ($m[1] as $js) {
				$this->assertTrue(is_file($this->root() . '/js/' . $js . '.js'), "$t: js/$js.js exists");
			}
		}
	}

	public function testPublicPagesAreStandaloneDocuments(): void {
		$src = (string)file_get_contents($this->root() . '/lib/Controller/PageController.php');
		$this->assertTrue(preg_match('/#\[PublicPage\]\s+#\[NoCSRFRequired\]\s+public function go\(\): StandalonePageResponse/', $src) === 1, 'go needs no login');
		$this->assertTrue(preg_match("/function go\(.*?standalone\('scan', \['data-code' => ''\]\)/s", $src) === 1);
		$this->assertTrue(preg_match('/public function scan\(string \$code\): StandalonePageResponse/', $src) === 1);
		$this->assertTrue(preg_match('/public function pair\(string \$token\): StandalonePageResponse/', $src) === 1);
		$this->assertFalse(str_contains($src, 'RENDER_AS_GUEST'), 'the Nextcloud guest layout brings its own manifest, icon and title');
		$resp = (string)file_get_contents($this->root() . '/lib/Http/StandalonePageResponse.php');
		foreach (['EmptyContentSecurityPolicy', 'EmptyFeaturePolicy', "'Content-Security-Policy'", "'Feature-Policy'", "'Permissions-Policy'"] as $needle) {
			$this->assertTrue(str_contains($resp, $needle), $needle);
		}
		$routes = require $this->root() . '/appinfo/routes.php';
		$urls = array_column($routes['routes'], 'url', 'name');
		$this->assertSame('/go', $urls['page#go']);
		$this->assertSame('/api/setup', $urls['setup#index']);
		$this->assertSame('/api/config', $urls['config#show']);
		$this->assertSame('/api/config', $urls['config#update']);
		$this->assertSame('/manifest', $urls['page#manifest']);
	}

	public function testStandaloneFilesExist(): void {
		foreach (StandalonePage::scripts() as $js) {
			$this->assertTrue(is_file($this->root() . '/js/' . $js . '.js'), "js/$js.js");
		}
		foreach (StandalonePage::styles() as $css) {
			$this->assertTrue(is_file($this->root() . '/css/' . $css . '.css'), "css/$css.css");
		}
		$this->assertSame('tg-boot', StandalonePage::scripts()[0]);
		$size = getimagesize($this->root() . '/img/apple-touch-icon.png');
		$this->assertSame([180, 180], [$size[0], $size[1]], 'apple-touch-icon is 180x180');
	}

	public function testMigrationAddsCodeHashAndFailTable(): void {
		$m = (string)file_get_contents($this->root() . '/lib/Migration/Version0002Date20261118000000.php');
		$this->assertStringContainsString("'code_hash'", $m);
		$this->assertStringContainsString("'tg_pairfail'", $m);
	}

	public function testServiceWorkerNeverTouchesNavigationOrApi(): void {
		$sw = (string)file_get_contents($this->root() . '/js/tg-sw.js');
		$this->assertFalse(str_contains($sw, 'offlinePage'), 'no offline page');
		$this->assertFalse(str_contains($sw, '<!doctype'), 'no generated HTML');
		$this->assertTrue(str_contains($sw, "req.mode === 'navigate'"), 'navigations are skipped explicitly');
		$this->assertTrue(str_contains($sw, "'/api/'"));
		$ctl = (string)file_get_contents($this->root() . '/lib/Controller/PageController.php');
		$this->assertTrue(str_contains($ctl, "addAllowedConnectDomain(\"'self'\")"), 'the worker script needs connect-src self or its fetch() fails');
		$this->assertTrue(str_contains($sw, 'skipWaiting') && str_contains($sw, 'clients.claim'));
		$this->assertTrue(preg_match("/CACHE = 'tg-shell-v([0-9]+)'/", $sw, $m) === 1 && (int)$m[1] >= 4, 'cache version raised');
	}

	public function testVersionIsBumpedAndDocumented(): void {
		$xml = (string)file_get_contents($this->root() . '/appinfo/info.xml');
		$this->assertTrue(preg_match('#<version>([0-9.]+)</version>#', $xml, $m) === 1);
		$this->assertTrue(version_compare($m[1], '0.1.3', '>='), 'asset cache busting needs a new version');
		$this->assertStringContainsString('## [' . $m[1] . ']', (string)file_get_contents($this->root() . '/CHANGELOG.md'));
	}
}
