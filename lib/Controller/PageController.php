<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Controller;

use OCA\TimeGuardian\AppInfo\Application;
use OCA\TimeGuardian\Domain\Manifest;
use OCA\TimeGuardian\Domain\Messages;
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

/** HTML pages. Templates are rendered by templates/{main,kiosk,scan,pair}.php (go uses scan without a code). */
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
	 * scanner here; an unpaired one gets the pairing instructions. Same template as the scan page, without a code.
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	public function go(): TemplateResponse {
		return new TemplateResponse(Application::APP_ID, 'scan', ['code' => ''], TemplateResponse::RENDER_AS_GUEST);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	public function scan(string $code): TemplateResponse {
		return new TemplateResponse(Application::APP_ID, 'scan', ['code' => $code], TemplateResponse::RENDER_AS_GUEST);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	public function pair(string $token): TemplateResponse {
		return new TemplateResponse(Application::APP_ID, 'pair', ['token' => $token], TemplateResponse::RENDER_AS_GUEST);
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
		$h = (string)$this->request->getHeader('Accept-Language');
		$m = Manifest::build($this->base(), [
			'192' => $icon('icon-192.png'),
			'512' => $icon('icon-512.png'),
			'maskable' => $icon('icon-maskable-512.png'),
			'svg' => $icon('app.svg'),
		], Messages::lang($h === '' ? null : substr($h, 0, 2)));
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
