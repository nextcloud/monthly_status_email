<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\MonthlyStatusEmail\Migration;

use OCA\MonthlyStatusEmail\Jobs\InitDatabaseJob;
use OCA\MonthlyStatusEmail\Service\NotificationTrackerService;
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
	}
}
