<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version100700Date20260507180000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('sakuraalbum_logs')) {
			$table = $schema->getTable('sakuraalbum_logs');
			if ($table->hasColumn('level')) {
				$table->changeColumn('level', [
					'notnull' => true,
					'length' => 16,
					'default' => 'info',
				]);
			}
		}

		return $schema;
	}
}
