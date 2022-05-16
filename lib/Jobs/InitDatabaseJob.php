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

declare(strict_types=1);

namespace OCA\MonthlyStatusEmail\Jobs;

use OCA\MonthlyStatusEmail\Service\NotificationTrackerService;
use OCP\BackgroundJob\QueuedJob;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUser;
use OCP\IUserManager;

class InitDatabaseJob extends QueuedJob {
	/**
	 * @var NotificationTrackerService
	 */
	private $service;
	/**
	 * @var IUserManager
	 */
	private $userManager;

	public function __construct(
		NotificationTrackerService $service,
		IUserManager $userManager,
		ITimeFactory $timeFactory
	) {
		parent::__construct($timeFactory);
		$this->service = $service;
		$this->userManager = $userManager;
	}

	protected function run($argument) {
		$this->userManager->callForAllUsers(function (IUser $user) {
			if ($user->getLastLogin() === 0) {
				// Don't send notifications to users who never logged in
				return;
			}
			try {
				// Spreads out reminders so that not every mails is sent at once.
				$randomNumberOfDays = new \DateInterval('P' . rand(0, 30) . 'D');
				$randomDateInLast30days = new \DateTime('now');
				$randomDateInLast30days->sub($randomNumberOfDays);
				$this->service->create($user->getUID(), false, $randomDateInLast30days->getTimestamp());
			} catch (\Exception $e) {
				// Already existing entry => ignore
			}
		});
	}
}
