<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Settings;

use OCA\SakuraAlbum\AppInfo\Application;
use OCA\SakuraAlbum\Service\SettingsService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\IDelegatedSettings;
use OCP\Util;

class Admin implements IDelegatedSettings {
	public function __construct(
		private readonly SettingsService $settingsService,
	) {
	}

	#[\Override]
	public function getForm(): TemplateResponse {
		Util::addStyle(Application::APP_ID, 'settings');
		Util::addScript(Application::APP_ID, 'admin-settings-1014');

		return new TemplateResponse(Application::APP_ID, 'settings/admin', [
			'settings' => $this->settingsService->getAdminSettings(),
		]);
	}

	#[\Override]
	public function getSection(): string {
		return Application::APP_ID;
	}

	#[\Override]
	public function getPriority(): int {
		return 10;
	}

	#[\Override]
	public function getName(): ?string {
		return null;
	}

	#[\Override]
	public function getAuthorizedAppConfig(): array {
		return [
			Application::APP_ID => ['/.*/'],
		];
	}
}
