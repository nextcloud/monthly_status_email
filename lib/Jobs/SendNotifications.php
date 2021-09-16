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

namespace OCA\MonthlyStatusEmail\Jobs;

use OC\Files\View;
use OCA\MonthlyStatusEmail\Db\NotificationTracker;
use OCA\MonthlyStatusEmail\Service\ClientDetector;
use OCA\MonthlyStatusEmail\Service\MessageProvider;
use OCA\MonthlyStatusEmail\Service\NotificationTrackerService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\DB\Exception;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Share\IManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

class SendNotifications extends TimedJob {
	public const NO_SHARE_AVAILABLE = 0;
	public const NO_CLIENT_CONNECTION = 1;
	public const NO_MOBILE_CLIENT_CONNECTION = 2;
	public const NO_DESKTOP_CLIENT_CONNECTION = 3;
	public const RECOMMEND_NEXTCLOUD = 4;
	public const TIP_FILE_RECOVERY = 5;
	public const TIP_EMAIL_CENTER = 6;
	public const TIP_MORE_STORAGE = 7;
	public const TIP_DISCOVER_PARTNER = 8;
	public const NO_FILE_UPLOAD = 9;
	public const NO_EMAIL_UPLOAD = 10;

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
	/**
	 * @var ClientDetector
	 */
	private $clientDetector;
	/**
	 * @var LoggerInterface
	 */
	private $logger;

	public function __construct(
		$appName,
		ITimeFactory $time,
		NotificationTrackerService $service,
		IUserManager $userManager,
		IConfig $config,
		IMailer $mailer,
		IL10N $l,
		IDBConnection $connection,
		IManager $shareManager,
		ClientDetector $clientDetector,
		LoggerInterface $logger
	) {
		parent::__construct($time);
		$this->setInterval(60 * 60); // every hour
		$this->service = $service;
		$this->userManager = $userManager;
		$this->config = $config;
		$this->appName = $appName;
		$this->mailer = $mailer;
		$this->l = $l;
		$this->shareManager = $shareManager;
		$this->entity = strip_tags($this->config->getAppValue('theming', 'name', 'Nextcloud'));
		$this->provider = \OC::$server->get($config->getSystemValueString('status-email-message-provider', MessageProvider::class));
		$this->connection = $connection;
		$this->clientDetector = $clientDetector;
		$this->logger = $logger;
	}

	/**
	 * @inheritDoc
	 */
	protected function run($argument): void {
		$limit = (int)$this->config->getAppValue($this->appName, 'status-email-max-mail-sent', '1000');

		// $trackedNotifications = $this->service->findAllOlderThan(new \DateTime("-1 month"), $limit);

		// for debugging
		$trackedNotifications = $this->service->findAllOlderThan(new \DateTime('now'), $limit);
		foreach ($trackedNotifications as $trackedNotification) {
			$this->processUser($trackedNotification);
		}
	}

	private function processUser(NotificationTracker $trackedNotification) {
		$message = $this->mailer->createMessage();
		$user = $this->userManager->get($trackedNotification->getUserId());
		$to = $user->getEMailAddress();
		if ($to === null) {
			// We don't have any email address, not sure what to do here.
			$trackedNotification->setOptedOut(true);
			// Try again next month
			$trackedNotification->setLastSendNotification(time());
			$this->service->update($trackedNotification);
			return;
		}
		$message->setFrom([$this->getFromAddress()]);
		$message->setTo([$to]);

		$emailTemplate = $this->mailer->createEMailTemplate('quote.notification');
		$emailTemplate->addHeader();
		$emailTemplate->setSubject(strip_tags($this->entity) . ' Status-Mail');

		// make sure FS is setup before querying storage related stuff...
		\OC_Util::setupFS($user->getUID());

		// Handle storage specific events
		$storageInfo = \OC_Helper::getStorageInfo('/');
		$stop = $this->provider->writeStorageUsage($emailTemplate, $storageInfo);

		if ($stop) {
			$this->provider->writeClosing($emailTemplate);
			$message->useTemplate($emailTemplate);
			$this->mailer->send($message);
			return;
		}

		if ($trackedNotification->getOptedOut()) {
			// People opting-out of the monthly emails should still get the
			// 'urgent' email about running out of storage, but the rest
			// shouldn't be sent.
			return;
		}

		// Handle no file upload
		$view = new View('/' . $user->getUID() . '/files/');
		$directoryContents = $view->getDirectoryContent('');
		if (count($directoryContents) === 0) {
			// No file/folder uploded
			$this->provider->writeGenericMessage($emailTemplate, $user, self::NO_FILE_UPLOAD);
			$this->provider->writeClosing($emailTemplate);
			$this->provider->writeOptOutMessage($emailTemplate, $trackedNotification);
			$message->useTemplate($emailTemplate);
			$this->mailer->send($message);
			return;
		}

		// Handle share specific events
		$shareCount = $this->handleShare($user);
		$this->provider->writeShareMessage($emailTemplate, $shareCount);

		// Add tips to randomly selected messages
		$availableGenericMessages = [self::TIP_DISCOVER_PARTNER, self::TIP_EMAIL_CENTER, self::TIP_FILE_RECOVERY, self::TIP_MORE_STORAGE];

		if ($shareCount === 0) {
			$availableGenericMessages[] = self::NO_SHARE_AVAILABLE;
		}

		// Handle desktop/mobile client connection detection
		$availableGenericMessages = array_merge($availableGenericMessages, $this->handleClientCondition($user));

		// Choose one of the less urgent message randomly
		$this->provider->writeGenericMessage($emailTemplate, $user, $availableGenericMessages[array_rand($availableGenericMessages)]);
		$this->provider->writeClosing($emailTemplate);
		$this->provider->writeOptOutMessage($emailTemplate, $trackedNotification);
		$message->useTemplate($emailTemplate);
		$this->mailer->send($message);

		$trackedNotification->setLastSendNotification(time());
		$this->service->update($trackedNotification);
	}

	private function handleShare(IUser $user): int {
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
		return $shareCount;
	}

	private function handleClientCondition(IUser $user): array {
		try {
			$hasDesktopClient = $this->clientDetector->hasDesktopConnection($user);
			$hasMobileClient = $this->clientDetector->hasMobileConnection($user);
		} catch (Exception $e) {
			$this->logger->error('There was an error when trying to detect mobile/desktop connection' . $e->getMessage(), [
				'exception' => $e
			]);
			return [];
		}

		$availableGenericMessages = [];

		if (!$hasDesktopClient && !$hasMobileClient) {
			$availableGenericMessages[] = self::NO_CLIENT_CONNECTION;
		} elseif (!$hasMobileClient) {
			$availableGenericMessages[] = self::NO_MOBILE_CLIENT_CONNECTION;
		} elseif (!$hasDesktopClient) {
			$availableGenericMessages[] = self::NO_DESKTOP_CLIENT_CONNECTION;
		}
		return $availableGenericMessages;
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
