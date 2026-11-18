<?php
declare(strict_types=1);
/**
 * Rendert eines der echten App-Templates mit Stub-Klassen (nur für den lokalen Mock-Server).
 * Aufruf: php render.php <template> <paramName> <paramValue>
 * Ausgabe: JSON {"html": "...", "scripts": [...], "styles": [...]}
 */
namespace OCP {
	class Util {
		public static array $scripts = [];
		public static array $styles = [];
		public static function addScript(string $app, string $file, string $after = 'core', bool $prepend = false): void { self::$scripts[] = $file; }
		public static function addStyle(string $app, ?string $file = null, bool $prepend = false): void { self::$styles[] = $file; }
	}
	interface IURLGenerator {}
	class Server {
		public static function get(string $c) {
			return new class implements IURLGenerator {
				public function linkToRoute(string $r, array $a = []): string {
					return match ($r) {
						'timeguardian.page.index' => '/apps/timeguardian/',
						'timeguardian.page.manifest' => '/apps/timeguardian/manifest',
						default => '/apps/timeguardian/' . $r,
					};
				}
				public function imagePath(string $app, string $f): string { return '/apps/timeguardian/img/' . $f; }
				public function linkTo(string $app, string $f): string { return '/apps/timeguardian/' . $f; }
			};
		}
	}
}
namespace {
	function p($s): void { echo htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
	[$self, $tpl, $name, $val] = array_pad($argv, 4, '');
	if ($tpl === 'scan' || $tpl === 'pair') {
		// go / scan / pair are standalone documents (Domain\StandalonePage), not templates
		foreach (['Manifest', 'Messages', 'StandalonePage'] as $c) {
			require_once __DIR__ . '/../../../lib/Domain/' . $c . '.php';
		}
		$b = '/apps/timeguardian';
		$f = fn (string $d, string $n, string $e) => $b . '/' . $d . '/' . $n . '.' . $e;
		echo json_encode(['standalone' => \OCA\TimeGuardian\Domain\StandalonePage::html([
			'lang' => 'de', 'title' => 'Zeit-Wächter', 'base' => $b, 'manifest' => $b . '/manifest', 'icon' => $b . '/img/icon-192.png',
			'apple' => $b . '/img/apple-touch-icon.png', 'jsqr' => $b . '/js/vendor/jsqr.js',
			'css' => array_map(fn ($n) => $f('css', $n, 'css'), \OCA\TimeGuardian\Domain\StandalonePage::styles()),
			'js' => array_map(fn ($n) => $f('js', $n, 'js'), \OCA\TimeGuardian\Domain\StandalonePage::scripts()),
			'page' => $tpl, 'attrs' => [$tpl === 'scan' ? 'data-code' : 'data-token' => $val], 'requesttoken' => '',
		])]);
		exit;
	}
	$_ = $name !== '' ? [$name => $val] : [];
	ob_start();
	include __DIR__ . '/../../../templates/' . basename($tpl) . '.php';
	$html = ob_get_clean();
	echo json_encode(['html' => $html, 'scripts' => \OCP\Util::$scripts, 'styles' => \OCP\Util::$styles]);
}
