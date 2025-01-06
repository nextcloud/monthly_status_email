<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\MonthlyStatusEmail\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version23000Date2021090112000001 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('notification_tracker')) {
			$table = $schema->createTable('notification_tracker');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 200
			]);
			$table->addColumn('last_send_notification', 'integer', [
				'notnull' => true,
			]);
			$table->addColumn('opted_out', 'boolean', [
				'notnull' => false,
				'default' => false,
			]);
			$table->addColumn('secret_token', 'string', [
				'notnull' => true,
				'default' => false,
			]);
			$table->addColumn('first_time_sent', 'boolean', [
				'notnull' => false,
				'default' => false,
			]);
			$table->addUniqueIndex(['user_id', 'secret_token']);

			$table->setPrimaryKey(['id']);
		}
		return $schema;
	}
}
