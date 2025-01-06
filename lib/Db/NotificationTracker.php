<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\MonthlyStatusEmail\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method setUserId(string $userId): void
 * @method setLastSendNotification(int $time): void
 * @method setOptedOut(bool $optedOut): void
 * @method setSecretToken(string $secretToken): void
 * @method setFirstTimeSent(bool $firstTimeSent): void
 * @method getUserId(): string
 * @method getLastSendNotification(): int
 * @method getOptedOut(): bool
 * @method getSecretToken(): string
 * @method getFirstTimeSent(): bool
 */
class NotificationTracker extends Entity implements \JsonSerializable {

	/** @var string $userId */
	protected $userId;

	/**
	 * This property holds the last time the user got sent a notification. Used
	 * to track when to send the next one. For easy comparison, the date is
	 * stored as a int.
	 * @var int $lastSendNotification
	 */
	protected $lastSendNotification;

	/**
	 * This property holds whether the user opt out of receiving montly email
	 * notifications.
	 * @var bool $optedOut
	 */
	protected $optedOut;

	/**
	 * This property holds a secret token to unsubscribe from the monthly
	 * notifications without getting logged.
	 * @var string $secretToken
	 */
	protected $secretToken;

	/**
	 * This property holds whether the first status mail was sent.
	 * @var bool $firstTimeSent
	 */
	protected $firstTimeSent;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('lastSendNotification', 'integer');
		$this->addType('optedOut', 'bool');
	}

	/** {@inheritDoc} */
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'user_id' => $this->userId,
			'last_send_notification' => $this->lastSendNotification,
			'opted_out' => $this->optedOut,
			'secret_token' => $this->secretToken,
			'first_time_sent' => $this->firstTimeSent,
		];
	}
}
