<?php

/**
 * @copyright Copyright (c) 2021 Carl Schwan <carl@carlschwan.eu>
 *
 * @author Carl Schwan <carl@carlschwan.eu>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\MonthlyNotifications\Listener;

use OCA\MonthlyNotifications\Service\NotificationTrackerService;
use OCP\IUser;
use Symfony\Component\EventDispatcher\GenericEvent;

class FirstLoginListener {
	/**
	 * @var NotificationTrackerService
	 */
	private $service;

	public function __construct(NotificationTrackerService $service) {
		$this->service = $service;
	}

	public function handle(GenericEvent $event): void {
		/** @var IUser $user */
		$user = $event->getSubject();
		$this->service->create($user->getUID(), false, time());
	}
}
