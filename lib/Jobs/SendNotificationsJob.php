<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\MonthlyStatusEmail\Jobs;

use OCA\MonthlyStatusEmail\Service\MailSender;
use OCA\MonthlyStatusEmail\Service\NotificationTrackerService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class SendNotificationsJob extends TimedJob {
	/**
	 * @var MailSender
	 */
	private $mailSender;
	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var string
	 */
	private $appName;
	/**
	 * @var NotificationTrackerService
	 */
	private $service;
	/**
	 * @var LoggerInterface
	 */
	private $logger;

	public function __construct(
		string $appName,
		ITimeFactory $time,
		MailSender $mailSender,
		IConfig $config,
		NotificationTrackerService $service,
		LoggerInterface $logger
	) {
		parent::__construct($time);
		$this->setInterval(0); // 60 * 60); // every hour
		$this->mailSender = $mailSender;
		$this->config = $config;
		$this->appName = $appName;
		$this->service = $service;
		$this->logger = $logger;
	}

	/**
	 * @inheritDoc
	 */
	protected function run($argument): void {
		$limit = (int)$this->config->getAppValue($this->appName, 'status-email-max-mail-sent', '1000');

		$trackedNotifications = $this->service->findAllOlderThan(new \DateTime("-1 month"), $limit);

		// for debugging
		// $trackedNotifications = $this->service->findAllOlderThan(new \DateTime('now'), $limit);
		foreach ($trackedNotifications as $trackedNotification) {
			try {
				$this->mailSender->sendMonthlyMailTo($trackedNotification);
			} catch (\Exception $e) {
				$this->logger->error("Coundln't send email to " . $trackedNotification->getUserId(), [
					'exception' => $e,
				]);
			}
		}
	}
}
