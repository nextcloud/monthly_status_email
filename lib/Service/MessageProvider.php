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
		$this->entiy = $config->getAppValue('theming', 'name', 'Nextcloud');
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
			$emailTemplate->addBodyText('Ihr Speicherplatz in der ' . $this->entiy . ' ist fast vollständing belegt. Sie können Ihren Speicherplatz jederzeit kostenpflichtig erweitern und dabei zwischen verschiedenen Speichergrößen wählen.');
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
			$emailTemplate->addBodyText('Ihr Speicherplatz in der ' . $this->entiy . ' ist vollständing belegt. Sie können Ihren Speicherplatz jederzeit kostenpflichtig erweitern und dabei zwischen verschiedenen Speichergrößen wählen.');
			$this->writeClosing($emailTemplate);
			return true;
		}
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
			$this->l->t('We will also give you some tips and tricks on how to use %s during your daily life. You can learn how to download, move, share files, etc. on our <a href="https://docs.nextcloud.com/server/latest/user_manual/en/">first start documentation</a>.',
				'We will also give you some tips and tricks on how to use %s during your daily life. You can learn how to download, move, share files, etc. on our [first start documentation](https://docs.nextcloud.com/server/latest/user_manual/en/).'
			)
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

	public function writeGenericMessage(IEMailTemplate $emailTemplate, IUser $user, string $messageId): void {
		$emailTemplate->addHeading('Hallo ' . $user->getDisplayName() . ',');

		switch ($emailTemplate) {
			case SendNotifications::NO_SHARE_AVAILABLE:
				$emailTemplate->addBodyText('bisher haben Sie keine Dateien order Ordner freigegeben.');
				$emailTemplate->addBodyText('Hochzeiten, Familienfeiern, gemeinsam verbrachte Urlaube - teilen Sie Ihre schönste ');
		}
	}
}
