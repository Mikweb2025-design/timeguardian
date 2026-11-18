<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Listener;

use OCA\TimeGuardian\Service\UserDataService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;

/** @template-implements IEventListener<UserDeletedEvent> deleting a Nextcloud user deletes all their time data */
class UserDeletedListener implements IEventListener {
	public function __construct(private UserDataService $data) {
	}

	public function handle(Event $event): void {
		if ($event instanceof UserDeletedEvent) {
			$this->data->deleteUser($event->getUser()->getUID());
		}
	}
}
