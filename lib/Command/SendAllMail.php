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
	public function __construct(
		private readonly NotificationTrackerService $service,
		private readonly MailSender $mailSender,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('monthly_status_email:send-all')
			->setDescription('Send the notification mail to all users');
		parent::configure();
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$trackedNotifications = $this->service->findAll();
		foreach ($trackedNotifications as $trackedNotification) {
			try {
				$ret = $this->mailSender->sendMonthlyMailTo($trackedNotification);
			} catch (\Exception $e) {
				$output->writeln('Failure sending email to ' . $trackedNotification->getUserId());
				continue;
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
