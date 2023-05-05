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
use OCP\IConfig;
use OCP\IServerContainer;
use OCP\IUser;
use OCP\Mail\IMailer;

// TODO port to IListener
class FirstLoginListener {
	/** @var IMailer */
	private $mailer;
	/** @var NotificationTrackerService */
	private $service;
	/** @var MessageProvider */
	private $provider;
	/** @var string */
	private $entity;
	/** @var bool */
	private $enabled;

	public function __construct(IMailer $mailer, NotificationTrackerService $service, IConfig $config, IServerContainer $container) {
		$this->enabled = $config->getSystemValueBool('status-email-send-first-login-mail', true);
		$this->mailer = $mailer;
		$this->service = $service;
		$this->entity = strip_tags($config->getAppValue('theming', 'name', 'Nextcloud'));
		$className = $config->getSystemValueString('status-email-message-provider', MessageProvider::class);
		if (class_exists($className)) {
			$this->provider = $container->get($className);
		} else {
			$this->provider = $container->get(MessageProvider::class);
		}
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

		// Initialise database
		$trackedNotification = $this->service->find($user->getUID());

		if (!$this->enabled) {
			return; // Feature is disabled, cancel sending initial login email
		}

		$message = $this->mailer->createMessage();
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
