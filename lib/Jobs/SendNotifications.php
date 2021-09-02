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
use OCP\Files\FileInfo;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Mail\IMailer;

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
	/**
	 * @var IL10N
	 */
	private $l;
	/**
	 * @var IURLGenerator
	 */
	private $generator;

	public function __construct($appName,
								ITimeFactory $time,
								NotificationTrackerService $service,
								IUserManager $userManager,
								IConfig $config,
								IMailer $mailer,
								IL10N $l,
								IURLGenerator $generator) {
		parent::__construct($time);
		//$this->setInterval(60 * 60 * 3); // every 3 hours
		$this->service = $service;
		$this->userManager = $userManager;
		$this->config = $config;
		$this->appName = $appName;
		$this->mailer = $mailer;
		$this->l = $l;
		$this->generator = $generator;
	}

	/**
	 * @inheritDoc
	 */
	protected function run($argument): void {
		$limit = (int)$this->config->getAppValue($this->appName, 'max_mail_sent', 100);

		//$trackedNotifications = $this->service->findAllOlderThan(new \DateTime("-1 month"), $limit);
		$trackedNotifications = $this->service->findAllOlderThan(new \DateTime('now'), $limit);
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
			$emailTemplate->addHeading('Hello ' . $user->getDisplayName());

			// make sure FS is setup before querying storage related stuff...
			\OC_Util::setupFS($user->getUID());

			$storageInfo = \OC_Helper::getStorageInfo('/');
			if ($storageInfo['quota'] === FileInfo::SPACE_UNLIMITED) {
				$totalSpace = $this->l->t('Unlimited');
			} else {
				$totalSpace = \OC_Helper::humanFileSize($storageInfo['total']);
			}
			$usedSpace = \OC_Helper::humanFileSize($storageInfo['used']);
			if ($storageInfo['quota'] !== FileInfo::SPACE_UNLIMITED) {
				$quota = \OC_Helper::humanFileSize($storageInfo['quota']);
				$emailTemplate->addBodyText(
					$this->l->t('You are currently using <strong>' . $usedSpace . '</strong> of <strong>' . $quota . '</strong>.'),
					$this->l->t('You are currently using ' . $usedSpace . ' of ' . $quota . '.')
				);
				if ($storageInfo['usage_relative'] > 80) {
					// todo display warning
					$emailTemplate->addBodyText($this->l->t('You are using over 80% of your quota.'));
				}
			} else {
				$emailTemplate->addBodyText(
					$this->l->t('You are currently using <strong>' . $usedSpace . '</strong> of storage.'),
					$this->l->t('You are currently using ' . $usedSpace . ' of storage.')
				);
			}
			$emailTemplate->addFooter($this->l->t('You can unsubscribe by clicking on this link: <a href="%s">Unsubscribe</a>', [
				$this->generator->getAbsoluteURL($this->generator->linkToRoute($this->appName.'.optout.displayOptingOutPage', [
					'token' => $trackedNotification->getSecretToken()
				]))
			]));
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
