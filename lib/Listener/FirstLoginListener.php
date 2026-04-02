<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\MonthlyStatusEmail\Listener;

use OCA\MonthlyStatusEmail\Service\MessageProvider;
use OCA\MonthlyStatusEmail\Service\NotFoundException;
use OCA\MonthlyStatusEmail\Service\NotificationTrackerService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\IServerContainer;
use OCP\IUser;
use OCP\Mail\IMailer;
use OCP\User\Events\UserFirstTimeLoggedInEvent;
use Psr\Container\ContainerInterface;

/**
 * @template-implements IEventListener<UserFirstTimeLoggedInEvent>
 */
class FirstLoginListener implements IEventListener {
	private MessageProvider $provider;
	private string $entity;
	private bool $enabled;

	public function __construct(
		private readonly IMailer $mailer,
		private readonly NotificationTrackerService $service,
		IConfig $config,
		ContainerInterface $container
	) {
		$this->enabled = $config->getSystemValueBool('status-email-send-first-login-mail', true);
		$this->entity = strip_tags($config->getAppValue('theming', 'name', 'Nextcloud'));
		$className = $config->getSystemValueString('status-email-message-provider', MessageProvider::class);
		if (class_exists($className)) {
			$this->provider = $container->get($className);
		} else {
			$this->provider = $container->get(MessageProvider::class);
		}
	}

	/**
	 * This method handles sending the welcome mail on first logging for new
	 * users.
	 * @throws NotFoundException
	 */
	public function handle(Event $event): void {
		if (!$event instanceof UserFirstTimeLoggedInEvent) {
			return;
		}
		$user = $event->getUser();

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
