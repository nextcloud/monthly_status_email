<?php

/**
 * @copyright 2021 Carl Schwan <carl@carlschwan.eu>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\MonthlyNotifications\Migration;

use OCA\MonthlyNotifications\Jobs\InitDatabaseJob;
use OCA\MonthlyNotifications\Service\NotificationTrackerService;
use OCP\BackgroundJob\IJobList;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class InitDatabase implements IRepairStep {

	/** @var IDBConnection */
	private $db;

	/** @var IJobList */
	private $jobList;

	/** @var IConfig */
	private $config;
	/**
	 * @var NotificationTrackerService
	 */
	private $service;

	/**
	 * @param IJobList $jobList
	 * @param IConfig $config
	 */
	public function __construct(
		IJobList $jobList,
		IConfig $config,
		NotificationTrackerService $service
	) {
		$this->jobList = $jobList;
		$this->config = $config;
		$this->service = $service;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return 'Registering filling out notification information for each user as a background job';
	}

	/**
	 * @param IOutput $output
	 */
	public function run(IOutput $output) {
		// only run once
		if ($this->config->getAppValue('monthly_status_email', 'init_db') === 'yes') {
			$output->info('Montly Notifications database already executed');
			return;
		}

		$output->info('Add background job');
		$this->jobList->add(InitDatabaseJob::class);

		// if all were done, no need to redo the repair during next upgrade
		$this->config->setAppValue('dav', 'buildCalendarSearchIndex', 'yes');
	}
}
