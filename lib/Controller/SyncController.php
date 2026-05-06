<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Controller;

use OCA\SakuraAlbum\AppInfo\Application;
use OCA\SakuraAlbum\Service\AlbumSyncService;
use OCA\SakuraAlbum\Service\AutoSyncService;
use OCA\SakuraAlbum\Service\SyncSafetyException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class SyncController extends Controller {
	public function __construct(
		IRequest $request,
		private readonly string $userId,
		private readonly AlbumSyncService $albumSyncService,
		private readonly AutoSyncService $autoSyncService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function dryRun(): JSONResponse {
		try {
			$settings = $this->request->getParam('settings', null);
			return new JSONResponse($this->albumSyncService->dryRun(
				$this->userId,
				is_array($settings) ? $settings : null,
			));
		} catch (SyncSafetyException $e) {
			return $this->safetyResponse($e);
		} catch (\Throwable) {
			return new JSONResponse([
				'error' => 'dry_run_failed',
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	#[NoAdminRequired]
	public function write(): JSONResponse {
		try {
			return new JSONResponse($this->albumSyncService->write(
				$this->userId,
				$this->stringParam('confirmation'),
				$this->stringParam('planFingerprint'),
			));
		} catch (SyncSafetyException $e) {
			return $this->safetyResponse($e);
		} catch (\Throwable) {
			return new JSONResponse([
				'error' => 'write_failed',
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	#[NoAdminRequired]
	public function runs(): JSONResponse {
		return new JSONResponse([
			'runs' => $this->albumSyncService->recentRuns($this->userId, $this->intParam('limit', 10, 1, 100)),
		]);
	}

	#[NoAdminRequired]
	public function status(): JSONResponse {
		return new JSONResponse([
			'runs' => $this->albumSyncService->recentRuns($this->userId, $this->intParam('limit', 5, 1, 20)),
			'queue' => $this->autoSyncService->queueStatusForUser($this->userId, $this->intParam('sampleLimit', 8, 1, 20)),
			'cursor' => $this->albumSyncService->cursorStatus($this->userId, 5),
		]);
	}

	#[NoAdminRequired]
	public function queueUpdate(): JSONResponse {
		return new JSONResponse($this->autoSyncService->queueUserRefresh($this->userId, 'manual_refresh'));
	}

	private function safetyResponse(SyncSafetyException $e): JSONResponse {
		return new JSONResponse([
			'error' => $e->getErrorCode(),
			'message' => $e->getMessage(),
			'details' => $e->getDetails(),
		], $e->getHttpStatus());
	}

	private function stringParam(string $key): string {
		$value = $this->request->getParam($key, '');
		return is_scalar($value) ? (string)$value : '';
	}

	private function intParam(string $key, int $default, int $min, int $max): int {
		$value = $this->request->getParam($key, $default);
		$value = is_numeric($value) ? (int)$value : $default;
		return max($min, min($max, $value));
	}
}
