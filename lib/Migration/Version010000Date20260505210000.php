<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010000Date20260505210000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('sakuraalbum_albums')) {
			$table = $schema->createTable('sakuraalbum_albums');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('photos_album_id', Types::BIGINT, [
				'notnull' => false,
				'length' => 20,
			]);
			$table->addColumn('album_name', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('source_root', Types::STRING, [
				'notnull' => true,
				'length' => 1024,
			]);
			$table->addColumn('target_path', Types::STRING, [
				'notnull' => true,
				'length' => 1024,
			]);
			$table->addColumn('naming_template', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('naming_schema_version', Types::INTEGER, [
				'notnull' => true,
				'default' => 1,
			]);
			$table->addColumn('config_hash', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('media_count', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('status', Types::STRING, [
				'notnull' => true,
				'length' => 32,
				'default' => 'planned',
			]);
			$table->addColumn('created_at', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('updated_at', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('last_sync_at', Types::BIGINT, [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'ska_album_user');
			$table->addIndex(['status'], 'ska_album_status');
			$table->addIndex(['photos_album_id'], 'ska_album_photo');
			$table->addUniqueIndex(['user_id', 'config_hash', 'target_path'], 'ska_album_identity');
		}

		if (!$schema->hasTable('sakuraalbum_runs')) {
			$table = $schema->createTable('sakuraalbum_runs');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('run_type', Types::STRING, [
				'notnull' => true,
				'length' => 32,
			]);
			$table->addColumn('status', Types::STRING, [
				'notnull' => true,
				'length' => 32,
			]);
			$table->addColumn('started_at', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('finished_at', Types::BIGINT, [
				'notnull' => false,
			]);
			$table->addColumn('summary_json', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('error_message', Types::TEXT, [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id', 'started_at'], 'ska_run_user_start');
			$table->addIndex(['status'], 'ska_run_status');
		}

		if (!$schema->hasTable('sakuraalbum_logs')) {
			$table = $schema->createTable('sakuraalbum_logs');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('level', Types::STRING, [
				'notnull' => true,
				'length' => 16,
			]);
			$table->addColumn('event', Types::STRING, [
				'notnull' => true,
				'length' => 96,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('run_id', Types::BIGINT, [
				'notnull' => false,
				'length' => 20,
			]);
			$table->addColumn('message', Types::STRING, [
				'notnull' => true,
				'length' => 512,
				'default' => '',
			]);
			$table->addColumn('context_json', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('created_at', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['created_at'], 'ska_log_created');
			$table->addIndex(['level', 'created_at'], 'ska_log_level_created');
			$table->addIndex(['user_id', 'created_at'], 'ska_log_user_created');
			$table->addIndex(['event'], 'ska_log_event');
		}

		return $schema;
	}
}
