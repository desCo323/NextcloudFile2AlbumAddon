<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Controller;

use OCA\SakuraAlbum\AppInfo\Application;
use OCA\SakuraAlbum\Service\AlbumSyncService;
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
				(string)$this->request->getParam('confirmation', ''),
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
		$limit = (int)$this->request->getParam('limit', 10);
		return new JSONResponse([
			'runs' => $this->albumSyncService->recentRuns($this->userId, $limit),
		]);
	}

	private function safetyResponse(SyncSafetyException $e): JSONResponse {
		return new JSONResponse([
			'error' => $e->getErrorCode(),
			'message' => $e->getMessage(),
			'details' => $e->getDetails(),
		], $e->getHttpStatus());
	}
}
