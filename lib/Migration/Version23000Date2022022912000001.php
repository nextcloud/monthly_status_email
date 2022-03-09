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

namespace OCA\MonthlyStatusEmail\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;
use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;

class Version23000Date2022022912000001 extends SimpleMigrationStep {

	/** @var IDBConnection */
	protected $connection;

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		$query = $this->connection->getQueryBuilder();

		// delete all but one entries for each user_id
		$query->selectAlias($query->func()->max('id'), 'newest_entry')
			->addSelect('user_id')
			->from('notification_tracker')
			->groupBy('user_id');

		$result = $query->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$delete = $this->connection->getQueryBuilder();
		$delete->delete('notification_tracker')
			->where($delete->expr()->eq('user_id', $delete->createParameter('user_id')))
			->andWhere($delete->expr()->neq('id', $delete->createParameter('id')));

		foreach ($rows as $row) {
			$delete->setParameter('user_id', $row['user_id'], IQueryBuilder::PARAM_STR)
				->setParameter('id', $row['newest_entry'], IQueryBuilder::PARAM_INT);
			$delete->executeStatement();
		}
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('notification_tracker');
		foreach ($table->getIndexes() as $index) {
			if ($index->spansColumns(['user_id', 'secret_token'])) {
				$table->dropIndex($index->getName());
			}
		}
		$table->addUniqueIndex(['user_id']);
		$table->addUniqueIndex(['secret_token']);

		return $schema;
	}
}
