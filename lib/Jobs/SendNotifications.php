<?php

declare(strict_types=1);

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

namespace OCA\MonthlyNotifications\Jobs;

use OCA\MonthlyNotifications\Service\NotificationTrackerService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\User\Backend\IGetRealUIDBackend;

class SendNotifications extends TimedJob {
	/** @var NotificationTrackerService $service */
	private $service;

	/** @var IUserManager $userManager */
	private $userManager;

	/** @var IConfig $config */
	private $config;

	/** @var string $appName */
	private $appName;
	/**
	 * @var IMailer
	 */
	private $mailer;

	public function __construct($appName,
								ITimeFactory $time,
								NotificationTrackerService $service,
								IUserManager $userManager,
								IConfig $config,
								IMailer $mailer) {
		$this->setInterval(60 * 60 * 3); // every 3 hours
		parent::__construct($time);
		$this->service = $service;
		$this->userManager = $userManager;
		$this->config = $config;
		$this->appName = $appName;
		$this->mailer = $mailer;
	}

	/**
	 * @inheritDoc
	 */
	protected function run($argument): void {
		$limit = $this->config->getAppValue($this->appName, 'max_mail_sent', 100);

		$trackedNotifications = $this->service->findAllOlderThan(new \DateTime("-1 month"), $limit);
		foreach ($trackedNotifications as $trackedNotification) {
			$message = $this->mailer->createMessage();
			$user = $this->userManager->get($trackedNotification->getUserId());
			$to = $user->getEMailAddress();
			if ($to === null) {
				// We don't have any email address, not sure what to do here.
				$trackedNotification->setOptedOut(true);
				continue;
			}
			$message->setFrom([$this->getFromAddress()]);
			$message->setTo([$to]);

			$emailTemplate = $this->mailer->createEMailTemplate('quote.notification');
			$emailTemplate->addHeader();
			$emailTemplate->addHeading('Welcome aboard');
			$emailTemplate->addBodyText('Quota: ' . $user->getQuota());
 			$emailTemplate->addFooter('Optional footer text');
 			$message->useTemplate($emailTemplate);
 			$this->mailer->send($message);
		}
	}

	private function getFromAddress():string {
		$sendFromDomain = $this->config->getSystemValue('mail_domain', 'domain.org');
		$sendFromAddress = $this->config->getSystemValue('mail_from_address', 'nextcloud');

		return implode('@', [
			$sendFromAddress,
			$sendFromDomain
		]);
	}
}
