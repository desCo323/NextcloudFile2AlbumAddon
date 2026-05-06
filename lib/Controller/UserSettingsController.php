<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Controller;

use OCA\SakuraAlbum\AppInfo\Application;
use OCA\SakuraAlbum\Service\AutoSyncService;
use OCA\SakuraAlbum\Service\LogService;
use OCA\SakuraAlbum\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class UserSettingsController extends Controller {
	public function __construct(
		IRequest $request,
		private readonly string $userId,
		private readonly SettingsService $settingsService,
		private readonly AutoSyncService $autoSyncService,
		private readonly LogService $logService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function get(): JSONResponse {
		$this->logService->debug('user_settings_read', $this->userId);
		return new JSONResponse([
			'settings' => $this->settingsService->getUserSettings($this->userId),
			'effectiveSettings' => $this->settingsService->getEffectiveUserSettings($this->userId),
			'adminSettings' => $this->settingsService->getAdminSettings(),
		]);
	}

	#[NoAdminRequired]
	public function update(): JSONResponse {
		try {
			$settings = $this->settingsService->saveUserSettings($this->userId, $this->request->getParam('settings', $this->request->getParams()));
			$effectiveSettings = $this->settingsService->getEffectiveUserSettings($this->userId);
			$queuedRefresh = $this->autoSyncService->queueUserRefresh($this->userId, 'settings_update');
			$this->logService->success('user_settings_updated', $this->userId, [
				'summary' => [
					'enabled' => $settings['enabled'],
					'includePaths' => count($settings['includePaths']),
					'sourceFolders' => count($settings['sourceFolders']),
					'folderRules' => count($settings['folderRules'] ?? []),
					'excludePatterns' => count($settings['excludePatterns']),
					'namingTemplate' => $settings['namingTemplate'],
					'albumDepth' => $settings['albumDepth'],
					'autoSyncEnabled' => $settings['autoSyncEnabled'],
					'queuedRefresh' => $queuedRefresh['queued'] ?? false,
				],
			], 'User settings saved.');

			return new JSONResponse([
				'settings' => $settings,
				'effectiveSettings' => $effectiveSettings,
				'queuedRefresh' => $queuedRefresh,
			]);
		} catch (\Throwable $e) {
			$this->logService->exception('user_settings_update_failed', $e, $this->userId);

			return new JSONResponse([
				'error' => 'user_settings_update_failed',
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
