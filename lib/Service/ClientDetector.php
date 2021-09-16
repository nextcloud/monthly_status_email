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

use OCP\DB\Exception;
use OCP\IDBConnection;
use OCP\IUser;

class ClientDetector {
	/**
	 * @var IDBConnection
	 */
	private $connection;

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	/**
	 * @throws Exception
	 */
	public function hasDesktopConnection(IUser $user): bool {
		$qb = $this->connection->getQueryBuilder();
		$hasDesktopClientQuery = $qb->from('authtoken')
			->select('name')
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($user->getUID())))
			->andWhere($qb->expr()->like('name', '%(Desktop Client -%'))
			->setMaxResults(1);
		return $this->connection->executeQuery($hasDesktopClientQuery->getSQL())->rowCount() === 1;
	}

	/**
	 * @throws Exception
	 */
	public function hasMobileConnection(IUser $user): bool {
		$qb = $this->connection->getQueryBuilder();
		$hasMobileClientQuery = $qb->from('authtoken')
			->select('name')
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($user->getUID())))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('name', 'Nextcloud-iOS'),
				$qb->expr()->like('name', '%Nextcloud-android%'),
			))
			->setMaxResults(1);
		return $this->connection->executeQuery($hasMobileClientQuery->getSQL())->rowCount() === 1;
	}
}
