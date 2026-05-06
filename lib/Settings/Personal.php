<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Settings;

use OCA\SakuraAlbum\AppInfo\Application;
use OCA\SakuraAlbum\Service\SettingsService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;
use OCP\Util;

class Personal implements ISettings {
	public function __construct(
		private readonly string $userId,
		private readonly SettingsService $settingsService,
	) {
	}

	#[\Override]
	public function getForm(): TemplateResponse {
		Util::addStyle(Application::APP_ID, 'settings');
		Util::addScript(Application::APP_ID, 'personal-settings-102');

		return new TemplateResponse(Application::APP_ID, 'settings/personal', [
			'settings' => $this->settingsService->getUserSettings($this->userId),
			'effectiveSettings' => $this->settingsService->getEffectiveUserSettings($this->userId),
			'adminSettings' => $this->settingsService->getAdminSettings(),
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
}
