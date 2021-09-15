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
use OCA\MonthlyNotifications\Jobs\SendNotifications;
use OCP\Files\FileInfo;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Mail\IEMailTemplate;

class MessageProvider {
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

	public function __construct(IConfig $config, IURLGenerator $generator) {
		$this->productName = $config->getAppValue('theming', 'productName', 'Nextcloud');
		$this->entity = $config->getAppValue('theming', 'name', 'Nextcloud');
		$this->generator = $generator;
		$this->config = $config;
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
	 * @param IEMailTemplate $emailTemplate
	 * @param array $storageInfo
	 * @return bool True if we should stop processing the condition after calling
	 * this method.
	 */
	public function writeStorageUsage(IEMailTemplate $emailTemplate, array $storageInfo): bool {
		$quota = $this->humanFileSize($storageInfo['quota']);
		$usedSpace = $this->humanFileSize($storageInfo['used']);

		$requestMoreStorageLink = $this->getRequestMoreStorageLink();
		if ($requestMoreStorageLink !== '') {
			$requestMoreStorageLink = '<p>' . $requestMoreStorageLink . '</p>';
		}

		if ($storageInfo['quota'] === FileInfo::SPACE_UNLIMITED) {
			// Message no quota
			$emailTemplate->addBodyText(<<<EOF
<table style="background-color: #f8f8f8; padding: 20px; width: 100%">
	<tr>
		<td>
			<span style="font-size: 35px">$usedSpace[0]</span> <span style="font-size: larger">$usedSpace[1]</span>
		</td>
		<td>
			<h3 style="font-weight: bold">Speicherplatz</h3>
			<p>Sie nutzen im Moment $usedSpace[0] $usedSpace[1].</p>
			$requestMoreStorageLink
		</td>
	</tr>
</table>
EOF,
				"Speicherplatz\n\nSie nutzen im Moment $usedSpace"
			);
			return false;
		} else if ($storageInfo['usage_relative'] < 90) {
			// Message quota but less than 90% used
			$emailTemplate->addBodyText(<<<EOF
<table style="background-color: #f8f8f8; padding: 20px; width: 100%">
	<tr>
		<td>
			<span style="font-size: 35px">$usedSpace[0]</span> <span style="font-size: larger">$usedSpace[1]</span>
			<hr />
			<span style="font-size: 35px">$quota[0]</span> <span style="font-size: larger">$quota[1]</span>
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
			return false;
		} else if ($storageInfo['usage_relative'] < 99) {
			// Warning almost no storage left
			$emailTemplate->addBodyText(<<<EOF
<table style="background-color: #f8f8f8; padding: 20px; width: 100%">
	<tr>
		<td>
			<span style="font-size: 35px; color: orange">$usedSpace[0]</span> <span style="font-size: larger">$usedSpace[1]</span>
			<hr />
			<span style="font-size: 35px">$quota[0]</span> <span style="font-size: larger">$quota[1]</span>
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
			return true;
		} else {
			// Warning no storage left
			$emailTemplate->addBodyText(<<<EOF
<table style="background-color: #f8f8f8; padding: 20px; width: 100%">
	<tr>
		<td>
			<span style="font-size: 35px; color: red">$usedSpace[0]</span> <span style="font-size: larger">$usedSpace[1]</span>
			<hr />
			<span style="font-size: 35px">$quota[0]</span> <span style="font-size: larger">$quota[1]</span>
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
			$emailTemplate->addBodyText('Ihr Speicherplatz in der ' . $this->entity . ' ist vollständing belegt. Sie können Ihren Speicherplatz jederzeit kostenpflichtig erweitern und dabei zwischen verschiedenen Speichergrößen wählen.');
			$this->writeClosing($emailTemplate);
			return true;
		}
	}

	public function writeWelcomeMail(IEMailTemplate $emailTemplate, string $name): void {
		$emailTemplate->addHeading("Welcome !", [$name]);

		$emailTemplate->addBodyText(
			'mit der Status-Mail zur   ' . $this->entity . ' informieren wir Sie einmail montatlich über Ihren belegten Speicherplatz und über Ihre erteilten Freigaben.',
			'mit der Status-Mail zur   ' . strip_tags($this->entity) . ' informieren wir Sie einmail montatlich über Ihren belegten Speicherplatz und über Ihre erteilten Freigaben.'
		);

		$emailTemplate->addBodyText(
			'Außerdem geben wir Ihnen Tipps und Tricks zum täaglichen Umgang mit Ihrer ' . $this->entity . '. Wie Sie Dateien hochlanden, verschieben, freigeben, etc., erfagren Sie hier: <a href="TODO">Erste Hilfe</a>',

			'Außerdem geben wir Ihnen Tipps und Tricks zum täaglichen Umgang mit Ihrer ' . strip_tags($this->entity) . '. Wie Sie Dateien hochlanden, verschieben, freigeben, etc., erfagren Sie hier: [Erste Hilfe](TODO)',
		);
	}

	public function writeShareMessage(IEMailTemplate $emailTemplate, int $shareCount) {
		if ($shareCount > 100) {
			$emailTemplate->addBodyText(<<<EOF
<div style="background-color: #f8f8f8; padding: 20px; float: right; margin-top: 50px">
	<h3 style="font-weight: bold">Freigaben</h3>
	<p>Sie haben mehr als $shareCount Dateien freigegeben.</p>
</div>
EOF,
				"Freigabeben\n\nSie haben mehr als $shareCount Dateien freigegeben."
			);
		} else if ($shareCount ===  0) {
			$emailTemplate->addBodyText(<<<EOF
<div style="background-color: #f8f8f8; padding: 20px; float: right; margin-top: 50px">
	<h3 style="font-weight: bold">Freigaben</h3>
	<p>Sie haben keine Dateien freigegeben.</p>
</div>
EOF,
				"Freigabeben\n\nSie haben kein Dateien freigegeben."
			);
		} else if ($shareCount ===  1) {
			$emailTemplate->addBodyText(<<<EOF
<div style="background-color: #f8f8f8; padding: 20px; float: right; margin-top: 50px">
	<h3 style="font-weight: bold">Freigaben</h3>
	<p>Sie haben eine Datein freigegeben.</p>
</div>
EOF,
				"Freigabeben\n\nSie haben eine Datei freigegeben."
			);
		}
	}

	public function writeOptOutMessage(IEMailTemplate $emailTemplate, NotificationTracker $trackedNotification) {
		$emailTemplate->addFooter($this->l->t('You can unsubscribe by clicking on this link: <a href="%s">Unsubscribe</a>', [
			$this->generator->getAbsoluteURL($this->generator->linkToRoute('monthly_notifications.optout.displayOptingOutPage', [
				'token' => $trackedNotification->getSecretToken()
			]))
		]));
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

		switch ($messageId) {
			case SendNotifications::NO_SHARE_AVAILABLE:
				$emailTemplate->addBodyText('bisher haben Sie keine Dateien order Ordner freigegeben.');
				$emailTemplate->addBodyText(
					'Hochzeiten, Familienfeiern, gemeinsam verbrachte Urlaube - teilen Sie Ihre schönste Momente jetzt ganz einfach mit Ihren Liebsten. Dies funktioniert ohne den umständlichen Austausch von Datenträgern. Auch Datein, die für einen E-Mail-Anhang zu groß sind, können Sie mit Ihrer ' . $this->entity . ' anderen bequem per Link zur Verfügung stellen.',
					'Hochzeiten, Familienfeiern, gemeinsam verbrachte Urlaube - teilen Sie Ihre schönste Momente jetzt ganz einfach mit Ihren Liebsten. Dies funktioniert ohne den umständlichen Austausch von Datenträgern. Auch Datein, die für einen E-Mail-Anhang zu groß sind, können Sie mit Ihrer ' . strip_tags($this->entity) . ' anderen bequem per Link zur Verfügung stellen.'
				);
				return;

			case SendNotifications::NO_DESKTOP_CLIENT_CONNECTION:
			case SendNotifications::NO_MOBILE_CLIENT_CONNECTION:
			case SendNotifications::NO_CLIENT_CONNECTION:
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
				return;

			case SendNotifications::NO_FILE_UPLOAD:
				$emailTemplate->addBodyText('TODO message to send then there is no file uploded');
				return;

			case SendNotifications::TIP_MORE_STORAGE:
				$emailTemplate->addBodyText('TODO message to advertise how to increase the storage outside of the out of storage place situation');
				return;

			case SendNotifications::TIP_DISCOVER_PARTNER:
				$emailTemplate->addBodyText('TODO message to advertise partners');
				return;

			case SendNotifications::TIP_FILE_RECOVERY:
				$emailTemplate->addBodyText('TODO message to explain how to recover data');
				return;

			case SendNotifications::TIP_EMAIL_CENTER:
				$emailTemplate->addBodyText('TODO message to explain the email center');
				return;
		}
	}
}
