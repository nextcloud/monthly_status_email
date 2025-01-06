<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\MonthlyStatusEmail\Jobs;

use OCA\MonthlyStatusEmail\Service\NotificationTrackerService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
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
		$this->userManager->callForSeenUsers(function (IUser $user): void {
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
