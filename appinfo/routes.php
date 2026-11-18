<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		// ---- pages --------------------------------------------------------------------------------------------
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'page#kiosk', 'url' => '/kiosk', 'verb' => 'GET'],
		['name' => 'page#go', 'url' => '/go', 'verb' => 'GET'],
		['name' => 'page#scan', 'url' => '/s/{code}', 'verb' => 'GET'],
		['name' => 'page#pair', 'url' => '/pair/{token}', 'verb' => 'GET'],
		['name' => 'page#manifest', 'url' => '/manifest', 'verb' => 'GET'],
		['name' => 'page#serviceWorker', 'url' => '/sw.js', 'verb' => 'GET'],

		// ---- own data -----------------------------------------------------------------------------------------
		['name' => 'me#me', 'url' => '/api/me', 'verb' => 'GET'],
		['name' => 'setup#index', 'url' => '/api/setup', 'verb' => 'GET'],
		['name' => 'config#show', 'url' => '/api/config', 'verb' => 'GET'],
		['name' => 'config#update', 'url' => '/api/config', 'verb' => 'PUT'],
		['name' => 'me#settings', 'url' => '/api/settings', 'verb' => 'GET'],
		['name' => 'me#updateSettings', 'url' => '/api/settings', 'verb' => 'PUT'],
		['name' => 'entry#index', 'url' => '/api/entries', 'verb' => 'GET'],
		['name' => 'entry#create', 'url' => '/api/entries', 'verb' => 'POST'],
		['name' => 'entry#update', 'url' => '/api/entries/{id}', 'verb' => 'PUT', 'requirements' => ['id' => '\d+']],
		['name' => 'entry#destroy', 'url' => '/api/entries/{id}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+']],
		['name' => 'report#week', 'url' => '/api/week', 'verb' => 'GET'],
		['name' => 'report#month', 'url' => '/api/month', 'verb' => 'GET'],
		['name' => 'report#account', 'url' => '/api/account', 'verb' => 'GET'],
		['name' => 'report#violations', 'url' => '/api/violations', 'verb' => 'GET'],

		// ---- punching (session OR X-TG-Device header) ---------------------------------------------------------
		['name' => 'punch#punch', 'url' => '/api/punch', 'verb' => 'POST'],
		['name' => 'punch#status', 'url' => '/api/status', 'verb' => 'GET'],

		// ---- pairing / devices --------------------------------------------------------------------------------
		['name' => 'device#pair', 'url' => '/api/pair', 'verb' => 'POST'],
		['name' => 'device#redeem', 'url' => '/api/pair/redeem', 'verb' => 'POST'],
		['name' => 'device#index', 'url' => '/api/devices', 'verb' => 'GET'],
		['name' => 'device#revoke', 'url' => '/api/devices/{id}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+']],

		// ---- absences / corrections ---------------------------------------------------------------------------
		['name' => 'absence#index', 'url' => '/api/absences', 'verb' => 'GET'],
		['name' => 'absence#create', 'url' => '/api/absences', 'verb' => 'POST'],
		['name' => 'absence#destroy', 'url' => '/api/absences/{id}', 'verb' => 'DELETE', 'requirements' => ['id' => '\d+']],
		['name' => 'absence#decide', 'url' => '/api/absences/{id}/decide', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
		['name' => 'correction#index', 'url' => '/api/corrections', 'verb' => 'GET'],
		['name' => 'correction#create', 'url' => '/api/corrections', 'verb' => 'POST'],
		['name' => 'correction#decide', 'url' => '/api/corrections/{id}/decide', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],

		// ---- manager ------------------------------------------------------------------------------------------
		['name' => 'team#overview', 'url' => '/api/team/overview', 'verb' => 'GET'],
		['name' => 'team#person', 'url' => '/api/team/person/{uid}', 'verb' => 'GET', 'requirements' => ['uid' => '[^/]+']],
		['name' => 'team#setTarget', 'url' => '/api/team/person/{uid}/target', 'verb' => 'PUT', 'requirements' => ['uid' => '[^/]+']],
		['name' => 'team#export', 'url' => '/api/team/export.csv', 'verb' => 'GET'],
		['name' => 'team#audit', 'url' => '/api/audit', 'verb' => 'GET'],

		// ---- export / kiosk / admin ---------------------------------------------------------------------------
		['name' => 'export#csv', 'url' => '/api/export.csv', 'verb' => 'GET'],
		['name' => 'export#json', 'url' => '/api/export.json', 'verb' => 'GET'],
		['name' => 'kiosk#code', 'url' => '/api/kiosk/code', 'verb' => 'GET'],
		['name' => 'admin#getConfig', 'url' => '/api/admin/config', 'verb' => 'GET'],
		['name' => 'admin#setConfig', 'url' => '/api/admin/config', 'verb' => 'POST'],

		// ---- server rendered settings forms (no JavaScript needed) -------------------------------------------
		['name' => 'settings_form#saveAdmin', 'url' => '/settings/admin', 'verb' => 'POST'],
		['name' => 'settings_form#savePersonal', 'url' => '/settings/personal', 'verb' => 'POST'],
		['name' => 'settings_form#revokeDevice', 'url' => '/settings/device/{id}/revoke', 'verb' => 'POST', 'requirements' => ['id' => '\d+']],
	],
];
