<?php
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
			->andWhere($qb->expr()->like('name', $qb->createNamedParameter('%(Desktop Client -%')))
			->setMaxResults(1);
		return $qb->executeQuery()->rowCount() === 1;
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
				$qb->expr()->eq('name', $qb->createNamedParameter('Nextcloud-iOS')),
				$qb->expr()->like('name', $qb->createNamedParameter('%Nextcloud-android%')),
			))
			->setMaxResults(1);
		return $qb->executeQuery()->rowCount() === 1;
	}
}
