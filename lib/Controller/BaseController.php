<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Controller;

use OCA\TimeGuardian\AppInfo\Application;
use OCA\TimeGuardian\Domain\Messages;
use OCA\TimeGuardian\Domain\TgException;
use OCA\TimeGuardian\Service\AccessService;
use OCA\TimeGuardian\Service\UserConfService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/** JSON helpers: uniform error format {error, message}, business errors mapped to HTTP status codes. */
abstract class BaseController extends Controller {
	private const STATUS = [
		'unauthorized' => Http::STATUS_UNAUTHORIZED,
		'forbidden' => Http::STATUS_FORBIDDEN,
		'not_in_team' => Http::STATUS_FORBIDDEN,
		'not_found' => Http::STATUS_NOT_FOUND,
		'validation' => Http::STATUS_BAD_REQUEST,
		'invalid_code' => Http::STATUS_BAD_REQUEST,
		'code_required' => Http::STATUS_BAD_REQUEST,
		'invalid_transition' => Http::STATUS_CONFLICT,
		'too_fast' => Http::STATUS_TOO_MANY_REQUESTS,
		'rate_limited' => Http::STATUS_TOO_MANY_REQUESTS,
		'locked' => Http::STATUS_CONFLICT,
		'overlap' => Http::STATUS_CONFLICT,
		'already_decided' => Http::STATUS_CONFLICT,
		'token_invalid' => Http::STATUS_BAD_REQUEST,
		'token_expired' => Http::STATUS_GONE,
		'token_used' => Http::STATUS_GONE,
		'csrf' => Http::STATUS_PRECONDITION_FAILED,
		'disabled' => Http::STATUS_CONFLICT,
	];

	public function __construct(
		IRequest $request,
		protected AccessService $access,
		protected UserConfService $userConf,
		protected LoggerInterface $logger,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/** language for messages: the caller's, else the browser's, else de */
	protected function lang(): string {
		$uid = $this->access->sessionUid();
		if ($uid !== null) {
			return $this->userConf->langOf($uid);
		}
		$h = (string)$this->request->getHeader('Accept-Language');
		return Messages::lang($h === '' ? null : substr($h, 0, 2));
	}

	protected function errorResponse(TgException $e): JSONResponse {
		$code = $e->errorCode();
		$body = ['error' => $code, 'message' => Messages::error($code, $this->lang())];
		if ($e->fields() !== []) {
			$body['fields'] = $e->fields();
			$body['message'] .= ' (' . implode(', ', $e->fields()) . ')';
		}
		foreach ($e->extra() as $k => $v) {
			$body[$k] = $v;
		}
		return new JSONResponse($body, self::STATUS[$code] ?? Http::STATUS_BAD_REQUEST);
	}

	/**
	 * Runs $fn and turns its array into JSON (or passes a Response through); business errors become error JSON.
	 * @param callable():(array|Response) $fn
	 */
	protected function run(callable $fn, int $status = Http::STATUS_OK): Response {
		try {
			$r = $fn();
			return $r instanceof Response ? $r : new JSONResponse($r, $status);
		} catch (TgException $e) {
			return $this->errorResponse($e);
		} catch (\Throwable $e) {
			$this->logger->error('TimeGuardian: ' . $e->getMessage(), ['app' => Application::APP_ID, 'exception' => $e]);
			return new JSONResponse(['error' => 'server_error', 'message' => Messages::error('server_error', $this->lang())], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/** JSON body of the request as array (works for JSON bodies and form posts) */
	protected function body(): array {
		$p = $this->request->getParams();
		unset($p['_route'], $p['id'], $p['uid']);
		return $p;
	}
}
