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

namespace OCA\MonthlyNotifications\Service;


use OCA\MonthlyNotifications\Db\NotificationTracker;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Mail\IEMailTemplate;

class MessageProvider {
	/** @var IL10N */
	private $l;
	/**
	 * @var string
	 */
	private $productName;
	/**
	 * @var IURLGenerator
	 */
	private $generator;
	/**
	 * @var IConfig
	 */
	private $config;

	public function __construct(IL10N $l, IConfig $config, IURLGenerator $generator) {
		$this->l = $l;
		$this->productName = strip_tags($config->getAppValue('theming', 'productName', 'Nextcloud'));
		$this->entiy = strip_tags($config->getAppValue('theming', 'name', 'Nextcloud'));
		$this->generator = $generator;
		$this->config = $config;
	}

	public function writeWelcomeMail(IEMailTemplate $emailTemplate, ?string $name): void {
		if ($name) {
			$emailTemplate->addHeading($this->l->t('Welcome %s!', [$name]));
		} else {
			$emailTemplate->addHeading($this->l->t('Welcome!'));
		}
		$emailTemplate->addBodyText(
			$this->l->t('with this status email about %s, we are informing you of your current storage usage and your current shares.', [$this->productName])
		);
		$emailTemplate->addBodyText(
			$this->l->t('We will also give you some tips and tricks on how to use %s during your daily life. You can learn how to download, move, share files, etc. on our <a href="https://docs.nextcloud.com/server/latest/user_manual/en/">first start documentation</a>.')
		);
	}

	public function writeOptOutMessage(IEMailTemplate $emailTemplate, NotificationTracker $trackedNotification) {
		$emailTemplate->addFooter($this->l->t('You can unsubscribe by clicking on this link: <a href="%s">Unsubscribe</a>', [
			$this->generator->getAbsoluteURL($this->generator->linkToRoute('monthly_notifications.optout.displayOptingOutPage', [
				'token' => $trackedNotification->getSecretToken()
			]))
		]));
	}

	public function getFromAddress():string {
		$sendFromDomain = $this->config->getSystemValue('mail_domain', 'domain.org');
		$sendFromAddress = $this->config->getSystemValue('mail_from_address', 'nextcloud');

		return implode('@', [
			$sendFromAddress,
			$sendFromDomain
		]);
	}
}
