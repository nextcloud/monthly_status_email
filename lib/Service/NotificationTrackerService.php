<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\MonthlyStatusEmail\Service;

use OCA\MonthlyStatusEmail\Db\NotificationTracker;
use OCA\MonthlyStatusEmail\Db\NotificationTrackerMapper;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;

class NotificationTrackerService {
	private $mapper;

	/**
	 * NotificationTrackerService constructor.
	 *
	 * @param NotificationTrackerMapper $mapper
	 */
	public function __construct(NotificationTrackerMapper $mapper) {
		$this->mapper = $mapper;
	}

	/**
	 * @throws NotFoundException
	 * @return never
	 */
	private function handleException(\Exception $e) {
		if ($e instanceof DoesNotExistException ||
			$e instanceof MultipleObjectsReturnedException) {
			throw new NotFoundException($e->getMessage());
		} else {
			throw $e;
		}
	}

	/**
	 * @param string $userId
	 *
	 * @return NotificationTracker
	 */
	public function find(string $userId): NotificationTracker {
		try {
			return $this->mapper->find($userId);
		} catch (\Exception $e) {
			return $this->create($userId, false, time());
		}
	}

	public function create(string $userId, bool $optedOut, int $time): NotificationTracker {
		$notificationTracker = new NotificationTracker();
		$notificationTracker->setUserId($userId);
		$notificationTracker->setLastSendNotification($time);
		$notificationTracker->setOptedOut($optedOut);
		$try = 0;
		while (true) {
			$notificationTracker->setSecretToken(bin2hex(random_bytes(20))); // cryptographically secure
			try {
				$this->mapper->insert($notificationTracker);
				break;
			} catch (Exception $exception) {
				$try++;
				if ($try > 10) {
					$this->handleException($exception);
				}
				// DB exception => secret token is probably already taken, try
				// 10 times before aborting.
				continue;
			} catch (\Exception $exception) {
				$this->handleException($exception);
			}
		}
		return $notificationTracker;
	}

	/**
	 * @throws NotFoundException
	 */
	public function updateOptedOut(string $userId, bool $optedOut): NotificationTracker {
		try {
			/** @var NotificationTracker $notificationTracker */
			$notificationTracker = $this->mapper->find($userId);
			$notificationTracker->setOptedOut($optedOut);
			$this->mapper->update($notificationTracker);
			return $notificationTracker;
		} catch (\Exception $e) {
			try {
				return $this->create($userId, $optedOut, time());
			} catch (\Exception $exception) {
				$this->handleException($exception);
			}
		}
	}

	/**
	 * @throws NotFoundException
	 */
	public function updateLastMessageSendTime(string $userId, int $time): NotificationTracker {
		try {
			/** @var NotificationTracker $notificationTracker */
			$notificationTracker = $this->mapper->find($userId);
			$notificationTracker->setLastSendNotification($time);
			$this->mapper->update($notificationTracker);
			return $notificationTracker;
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}

	/**
	 * @param \DateTimeInterface $date
	 * @return NotificationTracker[]
	 * @throws NotFoundException
	 */
	public function findAllOlderThan(\DateTimeInterface $date, int $limit): array {
		try {
			return $this->mapper->findAllOlderThan($date, $limit);
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}

	/** @return NotificationTracker[] */
	public function findAll(): array {
		try {
			return $this->mapper->findAll();
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}

	public function uptadeOptedOutByToken(string $token, bool $optedOut): void {
		try {
			$this->mapper->updateOptedOutByToken($token, $optedOut);
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}

	public function update(NotificationTracker $notificationTracker): void {
		$this->mapper->update($notificationTracker);
	}

	public function delete(NotificationTracker $notificationTracker): void {
		try {
			$this->mapper->delete($notificationTracker);
		} catch (Exception $e) {
			$this->handleException($e);
		}
	}
}
