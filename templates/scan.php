<?php
declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
use OCP\IURLGenerator;
use OCP\Server;
use OCP\Util;

/** @var array $_ */
$url = Server::get(IURLGenerator::class);
$base = $url->linkToRoute('timeguardian.page.index');
$manifest = $url->linkToRoute('timeguardian.page.manifest');
$icon = $url->imagePath('timeguardian', 'icon-192.png');
$apple = $url->imagePath('timeguardian', 'apple-touch-icon.png');
$jsqr = $url->linkTo('timeguardian', 'js/vendor/jsqr.js');

Util::addScript('timeguardian', 'tg-boot');
Util::addStyle('timeguardian', 'tg-common');
Util::addStyle('timeguardian', 'tg-punch');
Util::addScript('timeguardian', 'tg-core');
Util::addScript('timeguardian', 'tg-i18n');
Util::addScript('timeguardian', 'tg-api');
Util::addScript('timeguardian', 'tg-ui');
Util::addScript('timeguardian', 'tg-app');
Util::addScript('timeguardian', 'tg-install');
Util::addScript('timeguardian', 'tg-charts');
Util::addScript('timeguardian', 'tg-scanner');
Util::addScript('timeguardian', 'tg-punch');
?>
<div id="tg-root" data-page="scan" data-base="<?php p(rtrim($base, '/')); ?>" data-icon="<?php p($icon); ?>" data-apple="<?php p($apple); ?>" data-manifest="<?php p($manifest); ?>" data-jsqr="<?php p($jsqr); ?>" data-code="<?php p($_['code'] ?? ''); ?>">
	<noscript><p>TimeGuardian needs JavaScript.</p></noscript>
</div>
