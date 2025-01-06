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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SendMail extends Base {
	/** @var IUserManager $userManager */
	private $userManager;
	/** @var NotificationTrackerService */
	private $service;
	/** @var MailSender */
	private $mailSender;

	public function __construct(
		NotificationTrackerService $service,
		IUserManager $userManager,
		MailSender $mailSender
	) {
		parent::__construct();
		$this->userManager = $userManager;
		$this->service = $service;
		$this->mailSender = $mailSender;
	}

	protected function configure() {
		$this
						->setName('monthly_status_email:send')
						->setDescription('Send the notification mail to a specific user')
						->addArgument('user_id', InputArgument::REQUIRED, 'Id of the folder to configure');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$userId = $input->getArgument('user_id');
		$trackedNotification = $this->service->find($userId);
		$user = $this->userManager->get($trackedNotification->getUserId());
		if ($user === null) {
			$output->writeln('<error>User doesn\'t exist</error>');
			return 1;
		}

		$to = $user->getEMailAddress();
		if ($to === null) {
			// We don't have any email address, not sure what to do here.
			$output->writeln('<error>User doesn\'t have an email address</error>');
			return 1;
		}
		$ret = $this->mailSender->sendMonthlyMailTo($trackedNotification);
		if ($ret) {
			$output->writeln('Email sent to ' . $user->getDisplayName());
		} else {
			$output->writeln('Failure sending email to ' . $user->getDisplayName());
		}
		return 0;
	}
}
