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
use OCA\MonthlyStatusEmail\Jobs\SendNotificationsJob;
use OCP\Files\FileInfo;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\L10N\IFactory;
use OCP\Mail\IEMailTemplate;

class MessageProvider {
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
	/**
	 * @var string
	 */
	private $entity;
	/**
	 * @var IUser
	 */
	private $user;
	/**
	 * @var IL10N
	 */
	private $l10n;
	/**
	 * @var IFactory
	 */
	private $l10nFactory;

	public function __construct(IConfig $config, IURLGenerator $generator, IFactory $l10nFactory) {
		$this->productName = $config->getAppValue('theming', 'productName', 'Nextcloud');
		$this->entity = $config->getAppValue('theming', 'name', 'Nextcloud');
		$this->generator = $generator;
		$this->config = $config;
		$this->l10nFactory = $l10nFactory;
	}

	/**
	 * Similar to OC_Utils::humanFileSize but extract the units and number
	 * separately.
	 *
	 * @param int $bytes
	 * @return array|string[]
	 */
	protected function humanFileSize(int $bytes): array {
		if ($bytes < 0) {
			return ['?', ''];
		}
		if ($bytes < 1024) {
			return [$bytes, 'B'];
		}
		$bytes = round($bytes / 1024, 0);
		if ($bytes < 1024) {
			return [$bytes, 'KB'];
		}
		$bytes = round($bytes / 1024, 1);
		if ($bytes < 1024) {
			return [$bytes, 'MB'];
		}
		$bytes = round($bytes / 1024, 1);
		if ($bytes < 1024) {
			return [$bytes, 'GB'];
		}
		$bytes = round($bytes / 1024, 1);
		if ($bytes < 1024) {
			return [$bytes, 'TB'];
		}

		$bytes = round($bytes / 1024, 1);
		return [$bytes, 'PB'];
	}

	/**
	 * Link to get more storage or an empty string if not such
	 * functionality exists.
	 *
	 * @return string
	 */
	protected function getRequestMoreStorageLink(): string {
		return '';
	}

	/**
	 * Write generic end of mail. (e.g Thanks, Service name, ...)
	 */
	public function writeClosing(IEMailTemplate $emailTemplate): void {
		$emailTemplate->addBodyText('Your Nextcloud');
	}

	/**
	 * Storage used at more than 99%.
	 *
	 * @param IEMailTemplate $emailTemplate
	 * @param array $storageInfo
	 */
	public function writeStorageFull(IEMailTemplate $emailTemplate, array $storageInfo): void {
		$quota = $this->humanFileSize($storageInfo['quota']);
		$usedSpace = $this->humanFileSize($storageInfo['used']);

		$requestMoreStorageLink = $this->getRequestMoreStorageLink();
		if ($requestMoreStorageLink !== '') {
			$requestMoreStorageLink = '<p>' . $requestMoreStorageLink . '</p>';
		}

		// Warning no storage left
		$emailTemplate->addBodyText(
			<<<EOF
<table style="background-color: #f8f8f8; padding: 20px; width: 100%">
	<tr>
		<td style="padding-right: 20px; text-align: center">
			<span style="font-size: 35px; color: red">$usedSpace[0]</span>&nbsp;<span style="font-size: larger">$usedSpace[1]</span>
			<hr />
			<span style="font-size: 35px">$quota[0]</span>&nbsp;<span style="font-size: larger">$quota[1]</span>
		</td>
		<td>
			<h3 style="font-weight: bold">Speicherplatz</h3>
			<p>Sie nutzen im Moment $usedSpace[0] $usedSpace[1] von insgesammt $quota[0] $quota[1].</p>
			$requestMoreStorageLink
		</td>a
	</tr>
</table>
EOF,
			"Speicherplatz\n\nSie nutzen im Moment $usedSpace[0] $usedSpace[1] von insgesammt $quota[0] $quota[1]."
		);
		$emailTemplate->addHeading('Hallo,');
		$emailTemplate->addBodyText('Ihr Speicherplatz in der ' . $this->entity . ' ist vollständing belegt. Sie können Ihren Speicherplatz jederzeit kostenpflichtig erweitern und dabei zwischen verschiedenen Speichergrößen wählen.');
		$this->writeClosing($emailTemplate);
		$emailTemplate->addBodyButton('Jetzt Speicher erweitern', 'TODO');
	}

	public function writeStorageWarning(IEMailTemplate $emailTemplate, array $storageInfo): void {
		$quota = $this->humanFileSize($storageInfo['quota']);
		$usedSpace = $this->humanFileSize($storageInfo['used']);

		$requestMoreStorageLink = $this->getRequestMoreStorageLink();
		if ($requestMoreStorageLink !== '') {
			$requestMoreStorageLink = '<p>' . $requestMoreStorageLink . '</p>';
		}
		// Warning almost no storage left
		$emailTemplate->addBodyText(
			<<<EOF
<table style="background-color: #f8f8f8; padding: 20px; width: 100%">
	<tr>
		<td style="padding-right: 20px; text-align: center">
			<span style="font-size: 35px; color: orange">$usedSpace[0]</span>&nbsp;<span style="font-size: larger">$usedSpace[1]</span>
			<hr />
			<span style="font-size: 35px">$quota[0]</span>&nbsp;<span style="font-size: larger">$quota[1]</span>
		</td>
		<td>
			<h3 style="font-weight: bold">Speicherplatz</h3>
			<p>Sie nutzen im Moment $usedSpace[0] $usedSpace[1] von insgesammt $quota[0] $quota[1].</p>
			$requestMoreStorageLink
		</td>
	</tr>
</table>
EOF,
			"Speicherplatz\n\nSie nutzen im Moment $usedSpace[0] $usedSpace[1] von insgesammt $quota[0] $quota[1]."
		);
		$emailTemplate->addHeading('Hallo,');
		$emailTemplate->addBodyText('Ihr Speicherplatz in der ' . $this->entity . ' ist fast vollständing belegt. Sie können Ihren Speicherplatz jederzeit kostenpflichtig erweitern und dabei zwischen verschiedenen Speichergrößen wählen.');
		$this->writeClosing($emailTemplate);
		$emailTemplate->addBodyButton('Jetzt Speicher erweitern', 'TODO');
	}

	public function writeStorageNoQuota(IEMailTemplate $emailTemplate, array $storageInfo): void {
		$usedSpace = $this->humanFileSize($storageInfo['used']);
		// Message no quota
		$emailTemplate->addBodyText(
			<<<EOF
<table style="background-color: #f8f8f8; padding: 20px; width: 100%">
	<tr>
		<td style="padding-right: 20px; text-align: center">
			<span style="font-size: 35px">$usedSpace[0]</span>&nbsp;<span style="font-size: larger">$usedSpace[1]</span>
		</td>
		<td>
			<h3 style="font-weight: bold">Speicherplatz</h3>
			<p>Sie nutzen im Moment $usedSpace[0] $usedSpace[1].</p>
		</td>
	</tr>
</table>
EOF,
			"Speicherplatz\n\nSie nutzen im Moment $usedSpace[0] $usedSpace[1]"
		);
	}

	public function writeStorageSpaceLeft(IEMailTemplate $emailTemplate, array $storageInfo): void {
		$quota = $this->humanFileSize($storageInfo['quota']);
		$usedSpace = $this->humanFileSize($storageInfo['used']);

		$requestMoreStorageLink = $this->getRequestMoreStorageLink();
		if ($requestMoreStorageLink !== '') {
			$requestMoreStorageLink = '<p>' . $requestMoreStorageLink . '</p>';
		}

		$emailTemplate->addBodyText(
			<<<EOF
<table style="background-color: #f8f8f8; padding: 20px; width: 100%">
	<tr>
		<td style="padding-right: 20px; text-align: center">
			<span style="font-size: 35px">$usedSpace[0]</span>&nbsp;<span style="font-size: larger">$usedSpace[1]</span>
			<hr />
			<span style="font-size: 35px">$quota[0]</span>&nbsp;<span style="font-size: larger">$quota[1]</span>
		</td>
		<td>
			<h3 style="font-weight: bold">Speicherplatz</h3>
			<p>Sie nutzen im Moment $usedSpace[0] $usedSpace[1] von insgesammt $quota[0] $quota[1].</p>
			$requestMoreStorageLink
		</td>
	</tr>
</table>
EOF,
			"Speicherplatz\n\nSie nutzen im Moment $usedSpace[0] $usedSpace[1] von insgesammt $quota[0] $quota[1]."
		);
	}

	public function writeWelcomeMail(IEMailTemplate $emailTemplate, string $name): void {
		$emailTemplate->addHeading("Welcome $name !");

		$emailTemplate->addBodyText(
			'mit der Status-Mail zur ' . $this->entity . ' informieren wir Sie einmail montatlich über Ihren belegten Speicherplatz und über Ihre erteilten Freigaben.',
			'mit der Status-Mail zur ' . strip_tags($this->entity) . ' informieren wir Sie einmail montatlich über Ihren belegten Speicherplatz und über Ihre erteilten Freigaben.'
		);

		$emailTemplate->addBodyText(
			'Außerdem geben wir Ihnen Tipps und Tricks zum täaglichen Umgang mit Ihrer ' . $this->entity . '. Wie Sie Dateien hochlanden, verschieben, freigeben, etc., erfagren Sie hier: <a href="TODO">Erste Hilfe</a>',
			'Außerdem geben wir Ihnen Tipps und Tricks zum täaglichen Umgang mit Ihrer ' . strip_tags($this->entity) . '. Wie Sie Dateien hochlanden, verschieben, freigeben, etc., erfagren Sie hier: [Erste Hilfe](TODO)',
		);
	}

	public function writeShareMessage(IEMailTemplate $emailTemplate, int $shareCount) {
		if ($shareCount > 100) {
			$emailTemplate->addBodyText(
				<<<EOF
<div style="background-color: #f8f8f8; padding: 20px; float: right; margin-top: 50px">
	<h3 style="font-weight: bold">Freigaben</h3>
	<p>Sie haben mehr als $shareCount Dateien freigegeben.</p>
</div>
EOF,
				"Freigabeben\n\nSie haben mehr als $shareCount Dateien freigegeben."
			);
		} elseif ($shareCount === 0) {
			$emailTemplate->addBodyText(
				<<<EOF
<div style="background-color: #f8f8f8; padding: 20px; float: right; margin-top: 50px">
	<h3 style="font-weight: bold">Freigaben</h3>
	<p>Sie haben keine Dateien freigegeben.</p>
</div>
EOF,
				"Freigabeben\n\nSie haben kein Dateien freigegeben."
			);
		} elseif ($shareCount === 1) {
			$emailTemplate->addBodyText(
				<<<EOF
<div style="background-color: #f8f8f8; padding: 20px; float: right; margin-top: 50px">
	<h3 style="font-weight: bold">Freigaben</h3>
	<p>Sie haben eine Datei freigegeben.</p>
</div>
EOF,
				"Freigabeben\n\nSie haben eine Datei freigegeben."
			);
		} else {
			$emailTemplate->addBodyText(
				<<<EOF
<div style="background-color: #f8f8f8; padding: 20px; float: right; margin-top: 50px">
	<h3 style="font-weight: bold">Freigaben</h3>
	<p>Sie haben $shareCount Dateien freigegeben.</p>
</div>
EOF,
				"Freigabeben\n\nSie haben $shareCount Dateien freigegeben."
			);
		}
	}

	public function writeOptOutMessage(IEMailTemplate $emailTemplate, NotificationTracker $trackedNotification) {
		$emailTemplate->addFooter(sprintf(
			'Sie können die Status-Email <a href="%s">hier</a> abstellen',
			$this->generator->getAbsoluteURL($this->generator->linkToRoute('monthly_status_email.optout.displayOptingOutPage', [
				'token' => $trackedNotification->getSecretToken()
			]))
		));
	}

	public function getFromAddress(): string {
		$sendFromDomain = $this->config->getSystemValue('mail_domain', 'domain.org');
		$sendFromAddress = $this->config->getSystemValue('mail_from_address', 'nextcloud');

		return implode('@', [
			$sendFromAddress,
			$sendFromDomain
		]);
	}

	public function writeGenericMessage(IEMailTemplate $emailTemplate, IUser $user, int $messageId): void {
		$emailTemplate->addHeading('Hallo ' . $user->getDisplayName() . ',');
		$home = $this->generator->getAbsoluteURL('/');

		switch ($messageId) {
			case self::NO_SHARE_AVAILABLE:
				$emailTemplate->addBodyText('bisher haben Sie keine Dateien order Ordner freigegeben.');
				$emailTemplate->addBodyText(
					'Hochzeiten, Familienfeiern, gemeinsam verbrachte Urlaube - teilen Sie Ihre schönste Momente jetzt ganz einfach mit Ihren Liebsten. Dies funktioniert ohne den umständlichen Austausch von Datenträgern. Auch Datein, die für einen E-Mail-Anhang zu groß sind, können Sie mit Ihrer ' . $this->entity . ' anderen bequem per Link zur Verfügung stellen.',
					'Hochzeiten, Familienfeiern, gemeinsam verbrachte Urlaube - teilen Sie Ihre schönste Momente jetzt ganz einfach mit Ihren Liebsten. Dies funktioniert ohne den umständlichen Austausch von Datenträgern. Auch Datein, die für einen E-Mail-Anhang zu groß sind, können Sie mit Ihrer ' . strip_tags($this->entity) . ' anderen bequem per Link zur Verfügung stellen.'
				);
				$this->writeClosing($emailTemplate);
				$emailTemplate->addBodyButton($this->productName . ' öffnen', $home, strip_tags($this->productName) . ' öffnen');
				return;

			case self::NO_DESKTOP_CLIENT_CONNECTION:
			case self::NO_MOBILE_CLIENT_CONNECTION:
			case self::NO_CLIENT_CONNECTION:
				// TODO different message depending on if mobile, desktop or both apps weren't
				// used yet.
				// WARNING There might be some false positive in case an user renamed their devices
				// or deleted their client app token.
				$emailTemplate->addBodyText(
					'kennen Sie schon die kostenlose ' . $this->entity . ' Synchronisations-Software?',
					'kennen Sie schon die kostenlose ' . strip_tags($this->entity) . ' Synchronisations-Software?',
				);
				$emailTemplate->addBodyText(
					'Nach Download des kostenlose Software wird Ihre ' . $this->entity . ' als Ordner auf Ihrem Windows PC oder Mac angelegt. Alle Dateien, die Sie in diesen Ordner verschieben, werden automatisch mit Ihrer Cloud synchronisiert - so bleibt alles auf dem aktuellsten Stand. Öffnen Sie die Dateien aus Ihrer ' . $this->entity . ' mit Ihren gewohnten Anwendungen (z.B. Office) und machen Sie Äanderungen blitzschnell auf allen Geräten verfügbar.',
					'Nach Download des kostenlose Software wird Ihre ' . strip_tags($this->entity) . ' als Ordner auf Ihrem Windows PC oder Mac angelegt. Alle Dateien, die Sie in diesen Ordner verschieben, werden automatisch mit Ihrer Cloud synchronisiert - so bleibt alles auf dem aktuellsten Stand. Öffnen Sie die Dateien aus Ihrer ' . strip_tags($this->entity) . ' mit Ihren gewohnten Anwendungen (z.B. Office) und machen Sie Äanderungen blitzschnell auf allen Geräten verfügbar.'
				);
				$this->writeClosing($emailTemplate);
				$emailTemplate->addBodyButton('Zur ' . $this->productName . ' Sync Software', 'TODO', 'Zur ' . strip_tags($this->productName) . ' Sync Software');
				return;

			case self::NO_FILE_UPLOAD:
				$emailTemplate->addBodyText('TODO message to send then there is no file uploded');
				$this->writeClosing($emailTemplate);
				$emailTemplate->addBodyButton($this->productName . ' öffnen', $home, strip_tags($this->productName) . ' öffnen');
				return;

			case self::TIP_MORE_STORAGE:
				$emailTemplate->addBodyText('TODO message to advertise how to increase the storage outside of the out of storage place situation');
				$this->writeClosing($emailTemplate);
				$emailTemplate->addBodyButton($this->productName . ' öffnen', $home, strip_tags($this->productName) . ' öffnen');
				return;

			case self::TIP_DISCOVER_PARTNER:
				$emailTemplate->addBodyText('TODO message to advertise partners');
				$emailTemplate->addBodyButton($this->productName . ' öffnen', $home, strip_tags($this->productName) . ' öffnen');
				return;

			case self::TIP_FILE_RECOVERY:
				$emailTemplate->addBodyText('TODO message to explain how to recover data');
				$this->writeClosing($emailTemplate);
				$emailTemplate->addBodyButton($this->productName . ' öffnen', $home, strip_tags($this->productName) . ' öffnen');
				return;

			case self::TIP_EMAIL_CENTER:
				$emailTemplate->addBodyText('TODO message to explain the email center');
				$this->writeClosing($emailTemplate);
				$emailTemplate->addBodyButton($this->productName . ' öffnen', $home, strip_tags($this->productName) . ' öffnen');
				return;

			case self::RECOMMEND_NEXTCLOUD:
				$emailTemplate->addBodyText('TODO recommand service to family/friends');
				$this->writeClosing($emailTemplate);
				$emailTemplate->addBodyButton($this->productName . ' öffnen', $home, strip_tags($this->productName) . ' öffnen');
				return;

		}
	}

	public function setUser(IUser $user) {
		$this->user = $user;
		$this->l10n = $this->l10nFactory->get(
			'monthly_status_email',
			$this->config->getUserValue($user->getUID(), 'lang', null)
		);
	}
}
