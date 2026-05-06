<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version100400Date20260506234500 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('sakuraalbum_download_jobs')) {
			$table = $schema->createTable('sakuraalbum_download_jobs');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('source_type', Types::STRING, [
				'notnull' => true,
				'length' => 32,
			]);
			$table->addColumn('source_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('album_name', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('status', Types::STRING, [
				'notnull' => true,
				'length' => 32,
				'default' => 'pending',
			]);
			$table->addColumn('file_count', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('total_bytes', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('processed_files', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('processed_bytes', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('part_count', Types::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('output_path', Types::STRING, [
				'notnull' => false,
				'length' => 1024,
			]);
			$table->addColumn('result_json', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('last_error', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('created_at', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('updated_at', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('started_at', Types::BIGINT, [
				'notnull' => false,
			]);
			$table->addColumn('completed_at', Types::BIGINT, [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id', 'status'], 'ska_dl_user_status');
			$table->addIndex(['user_id', 'created_at'], 'ska_dl_user_created');
			$table->addIndex(['status', 'updated_at'], 'ska_dl_status_updated');
		}

		return $schema;
	}
}
