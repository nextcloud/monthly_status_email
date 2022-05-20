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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
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
