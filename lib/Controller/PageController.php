<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Controller;

use OCA\TimeGuardian\AppInfo\Application;
use OCA\TimeGuardian\Domain\Manifest;
use OCA\TimeGuardian\Domain\Messages;
use OCA\TimeGuardian\Domain\StandalonePage;
use OCA\TimeGuardian\Http\StandalonePageResponse;
use OCA\TimeGuardian\Service\AccessService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\EmptyContentSecurityPolicy;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;

/** HTML pages: main and kiosk use templates/ inside the Nextcloud layout; go, scan and pair are standalone documents (Domain\StandalonePage). */
class PageController extends Controller {
	public function __construct(
		IRequest $request,
		private IURLGenerator $urls,
		private AccessService $access,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		return new TemplateResponse(Application::APP_ID, 'main', [], TemplateResponse::RENDER_AS_USER);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function kiosk(): Response {
		$uid = $this->access->sessionUid();
		if ($uid === null || !$this->access->canKiosk($uid)) {
			return new RedirectResponse($this->urls->linkToRoute('timeguardian.page.index'));
		}
		return new TemplateResponse(Application::APP_ID, 'kiosk', [], TemplateResponse::RENDER_AS_USER);
	}

	/**
	 * Start page of the installed app: public, needs no Nextcloud login. A paired phone shows its status and the
	 * scanner here; an unpaired one gets the pairing instructions. Same page as /s/{code}, without a code.
	 * Delivered as a standalone document (own head) so that iOS "Add to Home Screen" sees our manifest, icon and title.
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	public function go(): StandalonePageResponse {
		return $this->standalone('scan', ['data-code' => '']);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	public function scan(string $code): StandalonePageResponse {
		return $this->standalone('scan', ['data-code' => $code]);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	public function pair(string $token): StandalonePageResponse {
		return $this->standalone('pair', ['data-token' => $token]);
	}

	/** @param array<string,string> $attrs */
	private function standalone(string $page, array $attrs): StandalonePageResponse {
		$lang = $this->lang();
		$name = Manifest::name($lang);
		$app = Application::APP_ID;
		$file = fn (string $dir, string $n, string $ext) => $this->urls->linkTo($app, $dir . '/' . $n . '.' . $ext);
		return new StandalonePageResponse(StandalonePage::html([
			'lang' => $lang,
			'title' => $name,
			'base' => rtrim($this->base(), '/'),
			'manifest' => $this->urls->linkToRoute('timeguardian.page.manifest'),
			'icon' => $this->urls->imagePath($app, 'icon-192.png'),
			'apple' => $this->urls->imagePath($app, 'apple-touch-icon.png'),
			'jsqr' => $this->urls->linkTo($app, 'js/vendor/jsqr.js'),
			'css' => array_map(fn (string $n) => $file('css', $n, 'css'), StandalonePage::styles()),
			'js' => array_map(fn (string $n) => $file('js', $n, 'js'), StandalonePage::scripts()),
			'page' => $page,
			'attrs' => $attrs,
			'requesttoken' => $this->requestToken(),
		]));
	}

	/** CSRF token for calls made with the Nextcloud session (a logged-in person without a paired phone). */
	private function requestToken(): string {
		try {
			return \OCP\Server::get(\OC\Security\CSRF\CsrfTokenManager::class)->getToken()->getEncryptedValue();
		} catch (\Throwable) {
			return '';
		}
	}

	private function lang(): string {
		$h = (string)$this->request->getHeader('Accept-Language');
		return Messages::lang($h === '' ? null : substr($h, 0, 2));
	}

	/** path of the app, e.g. /apps/timeguardian/ (or with index.php) */
	private function base(): string {
		$p = $this->urls->linkToRoute('timeguardian.page.index');
		return str_ends_with($p, '/') ? $p : $p . '/';
	}

	#[PublicPage]
	#[NoCSRFRequired]
	public function manifest(): DataDisplayResponse {
		$icon = fn (string $f) => $this->urls->imagePath(Application::APP_ID, $f);
		$m = Manifest::build($this->base(), [
			'192' => $icon('icon-192.png'),
			'512' => $icon('icon-512.png'),
			'maskable' => $icon('icon-maskable-512.png'),
			'svg' => $icon('app.svg'),
		], $this->lang());
		$r = new DataDisplayResponse(json_encode($m, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
		$r->addHeader('Content-Type', 'application/manifest+json');
		$r->addHeader('Cache-Control', 'no-cache');
		return $r;
	}

	#[PublicPage]
	#[NoCSRFRequired]
	public function serviceWorker(): DataDisplayResponse {
		$file = dirname(__DIR__, 2) . '/js/tg-sw.js';
		$js = is_file($file) ? (string)file_get_contents($file) : "self.addEventListener('fetch', function () {});\n";
		$r = new DataDisplayResponse($js);
		$r->addHeader('Content-Type', 'application/javascript');
		$r->addHeader('Service-Worker-Allowed', $this->base());
		// Nextcloud sends "default-src 'none'" with plain responses. The worker's own fetch() calls are governed by the
		// CSP of its script response, so without this every request made through the worker fails.
		$csp = new EmptyContentSecurityPolicy();
		$csp->addAllowedConnectDomain("'self'");
		$r->setContentSecurityPolicy($csp);
		$r->addHeader('Cache-Control', 'no-cache');
		return $r;
	}
}
