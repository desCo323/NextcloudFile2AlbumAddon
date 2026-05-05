<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\AppInfo;

use OCP\AppFramework\App;

class Application extends App {
	public const APP_ID = 'sakuraalbum';
	public const NAMING_SCHEMA_VERSION = 1;

	public function __construct() {
		parent::__construct(self::APP_ID);
	}
}
