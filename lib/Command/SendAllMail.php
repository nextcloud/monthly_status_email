<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\MonthlyStatusEmail\Command;

use OC\Core\Command\Base;
use OCA\MonthlyStatusEmail\Service\MailSender;
use OCA\MonthlyStatusEmail\Service\NotificationTrackerService;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SendAllMail extends Base {
	/** @var IUserManager $userManager */
	private $userManager;

	public function __construct(
		private readonly NotificationTrackerService $service,
		IUserManager $userManager,
		private readonly MailSender $mailSender,
	) {
		parent::__construct();
		$this->userManager = $userManager;
	}

	protected function configure() {
		$this
			->setName('monthly_status_email:send-all')
			->setDescription('Send the notification mail to all users');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$trackedNotifications = $this->service->findAll();
		foreach ($trackedNotifications as $trackedNotification) {
			try {
				$ret = $this->mailSender->sendMonthlyMailTo($trackedNotification);
			} catch (\Exception) {
				$output->writeln('Failure sending email to ' . $trackedNotification->getUserId());
			}
			if ($ret) {
				$output->writeln('Email sent to ' . $trackedNotification->getUserId());
			} else {
				$output->writeln('Failure sending email to ' . $trackedNotification->getUserId());
			}
		}
		return 0;
	}
}
