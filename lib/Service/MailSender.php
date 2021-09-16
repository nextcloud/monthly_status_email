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

namespace OCA\MonthlyStatusEmail\Service;

use OCA\MonthlyStatusEmail\Db\NotificationTracker;
use OCA\Talk\Model\Message;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use OCP\Share\IManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * Class MailSender
 *
 * @package OCA\MonthlyStatusEmail\Service
 */
class MailSender {

	/** @var NotificationTrackerService $service */
	private $service;

	/** @var IUserManager $userManager */
	private $userManager;

	/** @var IConfig $config */
	private $config;

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
	 * @var ClientDetector
	 */
	private $clientDetector;
	/**
	 * @var LoggerInterface
	 */
	private $logger;
	/**
	 * @var NoFileUploadedDetector
	 */
	private $noFileUploadedDetector;

	public function __construct(
		$appName,
		NotificationTrackerService $service,
		IUserManager $userManager,
		IConfig $config,
		IMailer $mailer,
		IL10N $l,
		IManager $shareManager,
		ClientDetector $clientDetector,
		LoggerInterface $logger,
		NoFileUploadedDetector $noFileUploadedDetector
	) {
		$this->service = $service;
		$this->userManager = $userManager;
		$this->config = $config;
		$this->appName = $appName;
		$this->mailer = $mailer;
		$this->l = $l;
		$this->shareManager = $shareManager;
		$this->entity = strip_tags($this->config->getAppValue('theming', 'name', 'Nextcloud'));
		$this->provider = \OC::$server->get($config->getSystemValueString('status-email-message-provider', MessageProvider::class));
		$this->clientDetector = $clientDetector;
		$this->logger = $logger;
		$this->noFileUploadedDetector = $noFileUploadedDetector;
	}

	private function setUpMail(IMessage $message, NotificationTracker $trackedNotification, IUser $user): ?IEMailTemplate {
		$to = $user->getEMailAddress();
		if ($to === null) {
			// We don't have any email address, not sure what to do here.
			$trackedNotification->setOptedOut(true);
			// Try again next month
			$trackedNotification->setLastSendNotification(time());
			$this->service->update($trackedNotification);
			return null;
		}
		$message->setFrom([$this->getFromAddress()]);
		$message->setTo([$to]);

		$emailTemplate = $this->mailer->createEMailTemplate('quote.notification');
		$emailTemplate->addHeader();
		$emailTemplate->setSubject(strip_tags($this->entity) . ' Status-Mail');
		return $emailTemplate;
	}

	public function sendMonthlyMailTo(NotificationTracker $trackedNotification) {
		$message = $this->mailer->createMessage();
		$user = $this->userManager->get($trackedNotification->getUserId());
		$emailTemplate = $this->setUpMail($message, $trackedNotification, $user);
		if ($emailTemplate === null) {
			return;
		}

		// make sure FS is setup before querying storage related stuff...
		\OC_Util::setupFS($user->getUID());

		// Handle storage specific events
		$storageInfo = \OC_Helper::getStorageInfo('/');
		$stop = $this->provider->writeStorageUsage($emailTemplate, $storageInfo);

		if ($stop) {
			// Urgent storage warning, show it and don't display anything else
			$this->sendEmail($emailTemplate, $user, $message);
			return;
		}

		if ($trackedNotification->getOptedOut()) {
			// People opting-out of the monthly emails should still get the
			// 'urgent' email about running out of storage, but the rest
			// shouldn't be sent.
			return;
		}

		// Handle no file upload
		if ($this->noFileUploadedDetector->hasNotUploadedFiles($user)) {
			// No file/folder uploaded
			$this->provider->writeGenericMessage($emailTemplate, $user, MessageProvider::NO_FILE_UPLOAD);
			$this->sendEmail($emailTemplate, $user, $message, $trackedNotification);
			return;
		}

		// Handle share specific events
		$shareCount = $this->handleShare($user);
		$this->provider->writeShareMessage($emailTemplate, $shareCount);

		// Add tips to randomly selected messages
		$availableGenericMessages = [MessageProvider::TIP_DISCOVER_PARTNER, MessageProvider::TIP_EMAIL_CENTER, MessageProvider::TIP_FILE_RECOVERY, MessageProvider::TIP_MORE_STORAGE];

		if ($shareCount === 0) {
			$availableGenericMessages[] = MessageProvider::NO_SHARE_AVAILABLE;
		}

		// Handle desktop/mobile client connection detection
		$availableGenericMessages = array_merge($availableGenericMessages, $this->handleClientCondition($user));

		// Choose one of the less urgent message randomly
		$this->provider->writeGenericMessage($emailTemplate, $user, $availableGenericMessages[array_rand($availableGenericMessages)]);
		$this->sendEmail($emailTemplate, $user, $message, $trackedNotification);
	}

	public function sendStatusEmailActivation(IUser $user, NotificationTracker $trackedNotification): void {
		$message = $this->mailer->createMessage();
		$user = $this->userManager->get($trackedNotification->getUserId());
		$emailTemplate = $this->setUpMail($message, $trackedNotification, $user);
		if ($emailTemplate === null) {
			return;
		}

		$this->provider->writeWelcomeMail($emailTemplate, $user->getDisplayName());
		$this->sendEmail($emailTemplate, $user, $message, $trackedNotification);
	}

	private function sendEmail(IEMailTemplate $template, IUser $user, IMessage $message, ?NotificationTracker $trackedNotification = null): void {
		if ($trackedNotification !== null) {
			$this->provider->writeOptOutMessage($template, $trackedNotification);
		}
		$message->useTemplate($template);
		try {
			$this->mailer->send($message);
		} catch (\Exception $e) {
			$this->logger->error('Error when sending status-email to ' . $user->getDisplayName() . ' with email address ' . $user->getEMailAddress() . ': ' . $e->getMessage(), [
				'exception' => $e
			]);
		}

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
			$availableGenericMessages[] = MessageProvider::NO_CLIENT_CONNECTION;
		} elseif (!$hasMobileClient) {
			$availableGenericMessages[] = MessageProvider::NO_MOBILE_CLIENT_CONNECTION;
		} elseif (!$hasDesktopClient) {
			$availableGenericMessages[] = MessageProvider::NO_DESKTOP_CLIENT_CONNECTION;
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
