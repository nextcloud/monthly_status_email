<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\MonthlyStatusEmail\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<NotificationTracker>
 */
class NotificationTrackerMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'notification_tracker', NotificationTracker::class);
	}

	public function find(string $userId) {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function updateOptedOutByToken(string $token, bool $optedOut): void {
		$qb = $this->db->getQueryBuilder();

		$qb->update($this->getTableName())
			->set('opted_out', $qb->createNamedParameter($optedOut))
			->where($qb->expr()->eq('secret_token', $qb->createNamedParameter($token)));
		$qb->executeStatement();
	}

	/**
	 * @param \DateTimeInterface $date
	 * @return NotificationTracker[]
	 * @throws \OCP\DB\Exception
	 */
	public function findAllOlderThan(\DateTimeInterface $date, int $limit): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->lt('last_send_notification', $qb->createNamedParameter($date->getTimestamp()))
			)
			->setMaxResults($limit);
		return $this->findEntities($qb);
	}

	/**
	 * @return NotificationTracker[]
	 * @throws \OCP\DB\Exception
	 */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName());
		return $this->findEntities($qb);
	}
}
