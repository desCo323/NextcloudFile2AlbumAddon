<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010600Date20260506113000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('sakuraalbum_dirty_paths')) {
			$table = $schema->createTable('sakuraalbum_dirty_paths');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('path', Types::STRING, [
				'notnull' => true,
				'length' => 1024,
			]);
			$table->addColumn('event_type', Types::STRING, [
				'notnull' => true,
				'length' => 32,
			]);
			$table->addColumn('status', Types::STRING, [
				'notnull' => true,
				'length' => 32,
				'default' => 'pending',
			]);
			$table->addColumn('change_count', Types::INTEGER, [
				'notnull' => true,
				'default' => 1,
			]);
			$table->addColumn('attempts', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('first_seen_at', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('last_seen_at', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('locked_at', Types::BIGINT, [
				'notnull' => false,
			]);
			$table->addColumn('last_error', Types::TEXT, [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['user_id', 'path'], 'ska_dirty_user_path');
			$table->addIndex(['status', 'last_seen_at'], 'ska_dirty_status_seen');
			$table->addIndex(['user_id', 'status'], 'ska_dirty_user_status');
		}

		return $schema;
	}
}
