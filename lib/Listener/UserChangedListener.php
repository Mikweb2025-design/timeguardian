<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Listener;

use OCA\TimeGuardian\Service\UserDataService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserChangedEvent;

/**
 * @template-implements IEventListener<UserChangedEvent>
 * A disabled user must not keep punch-clock access: all device tokens are revoked. The recorded data is kept
 * (statutory retention), only deletion of the user removes it.
 */
class UserChangedListener implements IEventListener {
	public function __construct(private UserDataService $data) {
	}

	public function handle(Event $event): void {
		if ($event instanceof UserChangedEvent && $event->getFeature() === 'enabled' && $event->getValue() === false) {
			$this->data->revokeDevices($event->getUser()->getUID());
		}
	}
}
