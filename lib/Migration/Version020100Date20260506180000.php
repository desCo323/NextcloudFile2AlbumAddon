<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version020100Date20260506180000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('sakuraalbum_sync_cursors')) {
			$table = $schema->createTable('sakuraalbum_sync_cursors');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('config_hash', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('status', Types::STRING, [
				'notnull' => true,
				'length' => 32,
				'default' => 'pending',
			]);
			$table->addColumn('cursor_path', Types::STRING, [
				'notnull' => false,
				'length' => 1024,
			]);
			$table->addColumn('processed_files', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('processed_albums', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('chunk_count', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('attempts', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('locked_at', Types::BIGINT, [
				'notnull' => false,
			]);
			$table->addColumn('created_at', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('updated_at', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('completed_at', Types::BIGINT, [
				'notnull' => false,
			]);
			$table->addColumn('summary_json', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('last_error', Types::TEXT, [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['user_id', 'config_hash'], 'ska_cursor_user_config');
			$table->addIndex(['user_id', 'status'], 'ska_cursor_user_status');
			$table->addIndex(['status', 'updated_at'], 'ska_cursor_status_updated');
		}

		return $schema;
	}
}
