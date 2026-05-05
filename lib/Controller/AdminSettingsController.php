<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Controller;

use OCA\SakuraAlbum\AppInfo\Application;
use OCA\SakuraAlbum\Service\LogService;
use OCA\SakuraAlbum\Service\SettingsService;
use OCA\SakuraAlbum\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class AdminSettingsController extends Controller {
	public function __construct(
		IRequest $request,
		private readonly SettingsService $settingsService,
		private readonly LogService $logService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function get(): JSONResponse {
		$this->logService->debug('admin_settings_read', null, ['route' => 'admin/settings']);
		return new JSONResponse([
			'settings' => $this->settingsService->getAdminSettings(),
		]);
	}

	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function update(): JSONResponse {
		try {
			$settings = $this->settingsService->saveAdminSettings($this->request->getParam('settings', $this->request->getParams()));
			$this->logService->success('admin_settings_updated', null, [
				'summary' => [
					'enabled' => $settings['enabled'],
					'debugMode' => $settings['debugMode'],
					'maxPreviewFolders' => $settings['maxPreviewFolders'],
					'maxPreviewFiles' => $settings['maxPreviewFiles'],
					'maxAlbumsPerRun' => $settings['maxAlbumsPerRun'],
				],
			], 'Admin settings saved.');

			return new JSONResponse([
				'settings' => $settings,
			]);
		} catch (\Throwable $e) {
			$this->logService->exception('admin_settings_update_failed', $e, null, [
				'requestKeys' => array_keys($this->request->getParams()),
			]);

			return new JSONResponse([
				'error' => 'admin_settings_update_failed',
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function logs(): JSONResponse {
		$limit = (int)$this->request->getParam('limit', 100);
		$level = $this->request->getParam('level', null);
		$userId = $this->request->getParam('userId', null);
		$logs = $this->logService->recentLogs($limit, is_string($level) ? $level : null, is_string($userId) ? $userId : null);
		$this->logService->debug('admin_logs_read', null, [
			'count' => count($logs),
			'limit' => $limit,
			'level' => $level,
			'userId' => $userId,
		]);

		return new JSONResponse([
			'logs' => $logs,
		]);
	}
}
