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

		$trackedNotifications = $this->service->findAllOlderThan(new \DateTime("-1 day"), $limit);

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
