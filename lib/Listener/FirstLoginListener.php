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

namespace OCA\MonthlyNotifications\Listener;

use OCA\MonthlyNotifications\Service\MessageProvider;
use OCA\MonthlyNotifications\Service\NotFoundException;
use OCA\MonthlyNotifications\Service\NotificationTrackerService;
use OCP\IUser;
use OCP\Mail\IMailer;
use Symfony\Component\EventDispatcher\GenericEvent;

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

	public function __construct(IMailer $mailer, NotificationTrackerService $service,
								MessageProvider $provider) {
		$this->mailer = $mailer;
		$this->service = $service;
		$this->provider = $provider;
	}

	/**
	 * This methods handles sending the welcome mail on first logging for new
	 * users.
	 * @param GenericEvent $event
	 * @throws NotFoundException
	 */
	public function handle(GenericEvent $event): void {
		/** @var IUser $user */
		$user = $event->getSubject();

		$message = $this->mailer->createMessage();
		$trackedNotification = $this->service->find($user->getUID());
		if (!$trackedNotification) {
			return;
		}
		$to = $user->getEMailAddress();
		if ($to === null) {
			// We don't have any email address ignore the users. We can't send
			// mails to them.
			return;
		}
		$message->setFrom([$this->provider->getFromAddress()]);
		$message->setTo([$to]);

		$emailTemplate = $this->mailer->createEMailTemplate('welcome.mail');
		$emailTemplate->addHeader();

		$this->provider->writeWelcomeMail($emailTemplate, $user->getDisplayName());
		$this->provider->writeOptOutMessage($emailTemplate, $trackedNotification);

		$message->useTemplate($emailTemplate);
		$this->mailer->send($message);
	}
}
