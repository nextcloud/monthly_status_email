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

namespace OCA\MonthlyStatusEmail\Listener;

use OCA\MonthlyStatusEmail\Service\MessageProvider;
use OCA\MonthlyStatusEmail\Service\NotFoundException;
use OCA\MonthlyStatusEmail\Service\NotificationTrackerService;
use OCP\IServerContainer;
use OCP\IUser;
use OCP\IConfig;
use OCP\Mail\IMailer;

// TODO port to IListener
class FirstLoginListener {
	/** @var IMailer */
	private $mailer;
	/** @var NotificationTrackerService */
	private $service;
	/**
	 * @var MessageProvider
	 */
	private $provider;
	/**
	 * @var string
	 */
	private $entity;

	public function __construct(IMailer $mailer, NotificationTrackerService $service, IConfig $config, IServerContainer $container) {
		$this->mailer = $mailer;
		$this->service = $service;
		$this->entity = strip_tags($config->getAppValue('theming', 'name', 'Nextcloud'));
		$this->provider = $container->get($config->getSystemValueString('status-email-message-provider', MessageProvider::class));
	}

	/**
	 * This methods handles sending the welcome mail on first logging for new
	 * users.
	 * @throws NotFoundException
	 */
	public function handle(IUser $user): void {
		$to = $user->getEMailAddress();
		if ($to === null) {
			// We don't have any email address ignore the users. We can't send
			// mails to them.
			return;
		}

		$message = $this->mailer->createMessage();
		$trackedNotification = $this->service->find($user->getUID());
		$message->setFrom([$this->provider->getFromAddress()]);
		$message->setTo([$to]);

		$emailTemplate = $this->mailer->createEMailTemplate('welcome.mail');
		$emailTemplate->setSubject(strip_tags($this->entity) . ' Status-Mail');
		$emailTemplate->addHeader();

		$this->provider->writeWelcomeMail($emailTemplate, $user->getDisplayName());
		$this->provider->writeOptOutMessage($emailTemplate, $trackedNotification);

		$message->useTemplate($emailTemplate);
		$this->mailer->send($message);
	}
}
