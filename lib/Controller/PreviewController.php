<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Controller;

use OCA\SakuraAlbum\AppInfo\Application;
use OCA\SakuraAlbum\Service\AlbumPlanService;
use OCA\SakuraAlbum\Service\LogService;
use OCA\SakuraAlbum\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class PreviewController extends Controller {
	public function __construct(
		IRequest $request,
		private readonly string $userId,
		private readonly SettingsService $settingsService,
		private readonly AlbumPlanService $albumPlanService,
		private readonly LogService $logService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function plan(): JSONResponse {
		$settings = $this->request->getParam('settings', []);
		if (!is_array($settings)) {
			$settings = [];
		}

		$started = microtime(true);
		$this->logService->debug('preview_requested', $this->userId, [
			'requestSettings' => $settings,
		]);
		try {
			$effectiveSettings = $this->settingsService->getEffectiveUserSettings($this->userId, $settings);
			$preview = $this->albumPlanService->buildPreview(
				$this->userId,
				$effectiveSettings,
				$this->settingsService->getPreviewLimits(),
			);
			$this->logService->success('preview_completed', $this->userId, [
				'durationMs' => (int)((microtime(true) - $started) * 1000),
				'summary' => $preview['summary'] ?? [],
				'warningCount' => count($preview['warnings'] ?? []),
			], 'Preview completed.');

			return new JSONResponse([
				'effectiveSettings' => $effectiveSettings,
				'preview' => $preview,
			]);
		} catch (\Throwable $e) {
			$this->logService->exception('preview_failed', $e, $this->userId, [
				'durationMs' => (int)((microtime(true) - $started) * 1000),
			]);

			return new JSONResponse([
				'error' => 'preview_failed',
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
