<?php
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\MonthlyStatusEmail\Tests;

use OCA\MonthlyStatusEmail\Db\NotificationTracker;
use OCA\MonthlyStatusEmail\Service\ClientDetector;
use OCA\MonthlyStatusEmail\Service\MailSender;
use OCA\MonthlyStatusEmail\Service\MessageProvider;
use OCA\MonthlyStatusEmail\Service\NoFileUploadedDetector;
use OCA\MonthlyStatusEmail\Service\NotificationTrackerService;
use OCA\MonthlyStatusEmail\Service\StorageInfoProvider;
use OCP\IL10N;
use OCP\IServerContainer;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use OCP\Share\IManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class MailSenderTest extends TestCase {
	/**
	 * @var NotificationTrackerService|MockObject
	 */
	private $service;
	/**
	 * @var IMailer|MockObject
	 */
	private $mailer;
	/**
	 * @var MessageProvider|MockObject
	 */
	private $provider;
	/**
	 * @var StorageInfoProvider|MockObject
	 */
	private $storageInfoProvider;
	/**
	 * @var ClientDetector|MockObject
	 */
	private $clientDetector;
	/**
	 * @var NoFileUploadedDetector|MockObject
	 */
	private $noFileUploadedDetector;
	/**
	 * @var IManager|MockObject
	 */
	private $shareManager;
	/**
	 * @var IUserManager|MockObject
	 */
	private $userManager;
	/**
	 * @var MailSender
	 */
	private $mailSender;
	/**
	 * @var IMessage
	 */
	private $message;
	/**
	 * @var NotificationTracker
	 */
	private $trackedNotification;
	/**
	 * @var IUser|MockObject
	 */
	private $user;
	/**
	 * @var IEMailTemplate|MockObject
	 */
	private $template;

	public function setUp(): void {
		parent::setUp();
		$config = \OC::$server->getConfig();
		$this->mailer = $this->createMock(IMailer::class);
		$this->service = $this->createMock(NotificationTrackerService::class);
		$this->config = $config;
		$this->container = $this->createMock(IServerContainer::class);
		$this->provider = $this->createMock(MessageProvider::class);
		$this->container->expects($this->any())
			->method('get')
			->willReturnCallback(function ($class) {
				if ($class === MessageProvider::class) {
					return $this->provider;
				}

				return \OC::$server->get($class);
			});
		$this->userManager = $this->createMock(IUserManager::class);
		$this->shareManager = $this->createMock(IManager::class);
		$this->clientDetector = $this->createMock(ClientDetector::class);
		$this->noFileUploadedDetector = $this->createMock(NoFileUploadedDetector::class);
		$this->storageInfoProvider = $this->createMock(StorageInfoProvider::class);

		$this->mailSender = new MailSender(
			$this->service,
			$this->userManager,
			$this->config,
			$this->mailer,
			$this->createMock(IL10N::class),
			$this->shareManager,
			$this->clientDetector,
			$this->createMock(LoggerInterface::class),
			$this->noFileUploadedDetector,
			$this->storageInfoProvider,
			$this->container
		);

		$this->message = $this->createMock(IMessage::class);
		$this->mailer->expects($this->once())
			->method('createMessage')
			->willReturn($this->message);

		$this->trackedNotification = new NotificationTracker();
		$this->trackedNotification->setOptedOut(false);
		$this->trackedNotification->setUserId('user1');
		$this->trackedNotification->setSecretToken('rere');
		$this->trackedNotification->setLastSendNotification(time());
		$this->trackedNotification->setFirstTimeSent(false);

		$this->user = $this->createMock(IUser::class);
		$this->user->expects($this->once())
			->method('getEmailAddress')
			->willReturn('user1@corp.corp');

		$this->userManager->expects($this->once())
			->method('get')
			->with('user1')
			->willReturn($this->user);

		$this->template = $this->createMock(IEMailTemplate::class);

		$this->mailer->expects($this->once())
			->method('createEmailTemplate')
			->withAnyParameters()
			->willReturn($this->template);
	}

	public function testStorageEmpty(): void {
		$this->message->expects($this->once())
			->method('useTemplate')
			->with($this->template);

		$this->storageInfoProvider->expects($this->once())
			->method('getStorageInfo')
			->willReturn([
				'quota' => 100,
				'used' => 100,
				'usage_relative' => 100,
			]);

		$this->provider->expects($this->once())
			->method('writeStorageFull')
			->withAnyParameters();
		$this->mailSender->sendMonthlyMailTo($this->trackedNotification);
	}

	public function testStorageWarning(): void {
		$this->message->expects($this->once())
			->method('useTemplate')
			->with($this->template);

		$this->storageInfoProvider->expects($this->once())
			->method('getStorageInfo')
			->willReturn([
				'quota' => 100,
				'used' => 95,
				'usage_relative' => 95,
			]);

		$this->provider->expects($this->once())
			->method('writeStorageWarning')
			->withAnyParameters();
		$this->mailSender->sendMonthlyMailTo($this->trackedNotification);
	}

	public function testStorageSafe(): void {
		$this->message->expects($this->once())
			->method('useTemplate')
			->with($this->template);

		$this->storageInfoProvider->expects($this->once())
			->method('getStorageInfo')
			->willReturn([
				'quota' => 100,
				'used' => 50,
				'usage_relative' => 50,
			]);

		$this->provider->expects($this->once())
			->method('writeStorageSpaceLeft')
			->withAnyParameters();

		$this->noFileUploadedDetector->expects($this->once())
			->method('hasNotUploadedFiles')
			->with($this->user)
			->willReturn(true);

		$this->shareManager->expects($this->atLeastOnce())
			->method('getSharesBy')
			->willReturn([]);

		$this->provider->expects($this->once())
			->method('writeGenericMessage')
			->with($this->template, $this->user, MessageProvider::NO_FILE_UPLOAD);

		$this->mailSender->sendMonthlyMailTo($this->trackedNotification);
	}

	public function testShare(): void {
		$this->message->expects($this->once())
			->method('useTemplate')
			->with($this->template);

		$this->storageInfoProvider->expects($this->once())
			->method('getStorageInfo')
			->willReturn([
				'quota' => 100,
				'used' => 50,
				'usage_relative' => 50,
			]);

		$this->provider->expects($this->once())
			->method('writeStorageSpaceLeft')
			->withAnyParameters();

		$this->noFileUploadedDetector->expects($this->once())
			->method('hasNotUploadedFiles')
			->with($this->user)
			->willReturn(false);

		$this->shareManager->expects($this->atLeastOnce())
			->method('getSharesBy')
			->willReturn([0]);

		$this->provider->expects($this->once())
			->method('writeShareMessage');

		$this->mailSender->sendMonthlyMailTo($this->trackedNotification);
	}

	public function testOptedOutUpdateTimestamp(): void {
		$this->storageInfoProvider->expects($this->once())
			->method('getStorageInfo')
			->willReturn([
				'quota' => 100,
				'used' => 50,
				'usage_relative' => 50,
			]);

		$this->trackedNotification->setOptedOut(true);

		$timestamp = $this->trackedNotification->getLastSendNotification();
		sleep(1);

		// NotificationTrackerService->update() gets TrackedNotification with updated timestamp
		$this->service
			->expects($this->once())
			->method('update')
			->willReturnCallback(function ($trackedNotification) use ($timestamp) {
				self::assertGreaterThan($timestamp, $trackedNotification->getLastSendNotification());
			});

		self::assertFalse($this->mailSender->sendMonthlyMailTo($this->trackedNotification));
	}
}
