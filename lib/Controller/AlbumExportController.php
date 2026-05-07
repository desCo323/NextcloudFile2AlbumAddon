<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Controller;

use OCA\SakuraAlbum\AppInfo\Application;
use OCA\SakuraAlbum\Service\AlbumExportService;
use OCA\SakuraAlbum\Service\SyncSafetyException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\StreamResponse;
use OCP\IRequest;

class AlbumExportController extends Controller {
	public function __construct(
		IRequest $request,
		private readonly string $userId,
		private readonly AlbumExportService $albumExportService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function albums(): JSONResponse {
		try {
			return new JSONResponse($this->albumExportService->listDownloadableAlbums(
				$this->userId,
				$this->intParam('limit', 200, 1, 500),
			));
		} catch (\Throwable) {
			return new JSONResponse([
				'error' => 'album_export_albums_failed',
				'message' => 'Album-Downloads konnten nicht geladen werden.',
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	#[NoAdminRequired]
	public function jobs(): JSONResponse {
		return new JSONResponse($this->albumExportService->jobsForUser(
			$this->userId,
			$this->intParam('limit', 20, 1, 100),
		));
	}

	#[NoAdminRequired]
	public function create(): JSONResponse {
		try {
			return new JSONResponse($this->albumExportService->createJob(
				$this->userId,
				$this->stringParam('sourceType'),
				$this->intParam('sourceId', 0, 1, PHP_INT_MAX),
			));
		} catch (SyncSafetyException $e) {
			return $this->safetyResponse($e);
		} catch (\Throwable) {
			return new JSONResponse([
				'error' => 'album_export_create_failed',
				'message' => 'Album-Export konnte nicht vorgemerkt werden.',
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	#[NoAdminRequired]
	public function download(): Response {
		try {
			$part = $this->albumExportService->downloadablePart(
				$this->userId,
				$this->intParam('jobId', 0, 1, PHP_INT_MAX),
				$this->intParam('part', 1, 1, PHP_INT_MAX),
			);
			$file = $part['file'];
			$handle = $file->fopen('rb');
			if (!is_resource($handle)) {
				throw new SyncSafetyException(
					'album_export_file_not_readable',
					'The prepared export file is not readable.',
					404,
				);
			}
			$response = new StreamResponse($handle);
			$response->addHeader('Content-Type', 'application/zip');
			$response->addHeader('Content-Length', (string)$file->getSize());
			$response->addHeader('Content-Disposition', $this->contentDisposition((string)$part['name']));

			return $response;
		} catch (SyncSafetyException $e) {
			return $this->safetyResponse($e);
		} catch (\Throwable) {
			return new JSONResponse([
				'error' => 'album_export_download_failed',
				'message' => 'Album-Export konnte nicht heruntergeladen werden.',
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
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

	private function contentDisposition(string $filename): string {
		$fallback = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $filename) ?? 'sakuraalbum-export.zip';
		$fallback = trim($fallback, " ._\t\n\r\0\x0B");
		if ($fallback === '') {
			$fallback = 'sakuraalbum-export.zip';
		}
		$fallback = str_replace(['\\', '"'], '_', mb_substr($fallback, 0, 150));

		return 'attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($filename);
	}
}
