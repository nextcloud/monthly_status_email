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

namespace OCA\MonthlyStatusEmail\Tests;

use OCA\MonthlyStatusEmail\Db\NotificationTracker;
use OCA\MonthlyStatusEmail\Listener\FirstLoginListener;
use OCA\MonthlyStatusEmail\Service\MessageProvider;
use OCA\MonthlyStatusEmail\Service\NotificationTrackerService;
use OCP\IConfig;
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
			->method('getUid')
			->willReturn('user1');
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
