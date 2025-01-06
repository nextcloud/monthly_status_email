<?php
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\MonthlyStatusEmail\Tests;

use OCA\MonthlyStatusEmail\Db\NotificationTracker;
use OCA\MonthlyStatusEmail\Listener\FirstLoginListener;
use OCA\MonthlyStatusEmail\Service\MessageProvider;
use OCA\MonthlyStatusEmail\Service\NotificationTrackerService;
use OCP\IServerContainer;
use OCP\IUser;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use Test\TestCase;

class InitialEmailTest extends TestCase {
	/**
	 * @var NotificationTrackerService|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $service;
	/**
	 * @var IMailer|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $mailer;
	/**
	 * @var FirstLoginListener
	 */
	private $firstLoginListener;
	/**
	 * @var MessageProvider|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $provider;

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
				} else {
					return \OC::$server->get($class);
				}
			});
		$this->firstLoginListener = new FirstLoginListener($this->mailer, $this->service, $this->config, $this->container);
	}

	public function testUserNoEmail() {
		$user = $this->createMock(IUser::class);
		$user->expects($this->once())
			->method('getEmailAddress')
			->willReturn(null);

		$this->service->expects($this->never())
			->method('find');

		$this->firstLoginListener->handle($user);
	}

	public function testSendEmail() {
		$user = $this->createMock(IUser::class);
		$user->expects($this->once())
			->method('getUid')
			->willReturn('user1');
		$user->expects($this->any())
			->method('getDisplayName')
			->willReturn('Foo Bar');
		$user->expects($this->once())
			->method('getEmailAddress')
			->willReturn('foo@bar.corp');

		$trackedNotification = new NotificationTracker();
		$trackedNotification->setOptedOut(false);
		$trackedNotification->setUserId('user1');
		$trackedNotification->setSecretToken('rere');
		$trackedNotification->setLastSendNotification(time());
		$trackedNotification->setFirstTimeSent(false);

		$this->service->expects($this->once())
			->method('find')
			->with('user1')
			->willReturn($trackedNotification);

		$template = $this->createMock(IEMailTemplate::class);

		$this->mailer->expects($this->once())
			->method('createEmailTemplate')
			->withAnyParameters()
			->willReturn($template);

		$this->provider->expects($this->once())
			->method('writeOptOutMessage')
			->withAnyParameters();

		$this->firstLoginListener->handle($user);
	}
}
