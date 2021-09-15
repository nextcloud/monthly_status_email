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

use OCA\MonthlyNotifications\Db\NotificationTracker;
use OCA\MonthlyNotifications\Service\MessageProvider;
use OCA\MonthlyNotifications\Service\NotificationTrackerService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\Files\FileInfo;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Share\IManager;
use OCP\Share\IShare;
use OC\Authentication\Token\INamedToken;
use OC\Authentication\Token\IProvider as IAuthTokenProvider;
use OC\Authentication\Token\IToken;

class SendNotifications extends TimedJob {
	public const NO_SHARE_AVAILABLE = 0;
	public const NO_CLIENT_CONNECTION = 1;
	public const NO_MOBILE_CLIENT_CONNECTION = 2;
	public const NO_DESKTOP_CLIENT_CONNECTION = 3;

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
	 * @var IManager
	 */
	private $shareManager;
	/**
	 * @var string
	 */
	private $entity;
	/**
	 * @var MessageProvider
	 */
	private $provider;
	/**
	 * @var IDBConnection
	 */
	private $connection;

	public function __construct($appName,
								ITimeFactory $time,
								NotificationTrackerService $service,
								IUserManager $userManager,
								IConfig $config,
								IMailer $mailer,
								IL10N $l,
								IDBConnection $connection,
								IManager $shareManager,
								MessageProvider $provider) {
		parent::__construct($time);
		//$this->setInterval(60 * 60 * 3); // every 3 hours
		$this->service = $service;
		$this->userManager = $userManager;
		$this->config = $config;
		$this->appName = $appName;
		$this->mailer = $mailer;
		$this->l = $l;
		$this->shareManager = $shareManager;
		$this->entity = strip_tags($this->config->getAppValue('theming', 'name', 'Nextcloud'));
		$this->provider = $provider;
		$this->connection = $connection;
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

			// make sure FS is setup before querying storage related stuff...
			\OC_Util::setupFS($user->getUID());

			// Handle storage specific events
			$storageInfo = \OC_Helper::getStorageInfo('/');
			$stop = $this->provider->writeStorageUsage($emailTemplate, $storageInfo);

			if ($stop) {
				$this->provider->writeClosing($emailTemplate);
				$this->provider->writeOptOutMessage($emailTemplate, $trackedNotification);
				continue;
			}

			// Handle share specific events
			$requestedShareTypes = [
				IShare::TYPE_USER,
				IShare::TYPE_GROUP,
				IShare::TYPE_LINK,
				IShare::TYPE_REMOTE,
				IShare::TYPE_EMAIL,
				IShare::TYPE_ROOM,
				IShare::TYPE_CIRCLE,
				IShare::TYPE_DECK,
			];
			$shareCount = 0;
			foreach ($requestedShareTypes as $requestedShareType) {
				$shares = $this->shareManager->getSharesBy(
					$user->getUID(),
					$requestedShareType,
					null,
					false,
					100
				);
				$shareCount += count($shares);
				if ($shareCount > 100) {
					break; // don't
				}
			}

			$this->provider->writeShareMessage($emailTemplate, $shareCount);

			$availableGenericMessages = [];

			if ($shareCount === 0) {
				$availableGenericMessages[] = self::NO_SHARE_AVAILABLE;
			}

			// Handling of desktop/mobile client detection
			$qb = $this->connection->getQueryBuilder();
			$hasDesktopClientQuery = $qb->from('authtoken')
				->select('name')
				->where($qb->expr()->eq('uid', $qb->createNamedParameter($user->getUID())))
				->andWhere($qb->expr()->like('name', '%(Desktop Client -%'))
				->setMaxResults(1);
			$this->connection->executeQuery($hasDesktopClientQuery->getSQL());

			$hasMobileClientQuery = $qb->from('authtoken')
				->select('name')
				->where($qb->expr()->eq('uid', $qb->createNamedParameter($user->getUID())))
				->andWhere($qb->expr()->orX(
					$qb->expr()->eq('name', 'Nextcloud-iOS'),
					$qb->expr()->like('name', '%Nextcloud-android%'),
				))
				->setMaxResults(1);
			$this->connection->executeQuery($hasDesktopClientQuery->getSQL());

			if (!$hasDesktopClientQuery && !$hasMobileClientQuery) {
				$availableGenericMessages[] = self::NO_CLIENT_CONNECTION;
			} elseif (!$hasMobileClientQuery) {
				$availableGenericMessages[] = self::NO_MOBILE_CLIENT_CONNECTION;
			} elseif (!$hasDesktopClientQuery) {
				$availableGenericMessages[] = self::NO_DESKTOP_CLIENT_CONNECTION;
			}

			$this->provider->writeGenericMessage($emailTemplate, $user, $availableGenericMessages[array_rand($availableGenericMessages)]);

			$this->provider->writeOptOutMessage($emailTemplate, $trackedNotification);
			$message->useTemplate($emailTemplate);
			$this->mailer->send($message);
			return;
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

	private function generateWelcomeMail(IEMailTemplate $emailTemplate, IUser $user): IEMailTemplate {
		$emailTemplate->addBodyText(
			$this->l->t('with this status email from %s we inform you once a month about your storage usage and your storage quota.', [$this->entity])
		);
		$emailTemplate->addBodyText(
			$this->l->t('Additionally we give you ')
		);

		return $emailTemplate;
	}
}
