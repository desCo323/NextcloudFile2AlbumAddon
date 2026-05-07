<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Controller;

use OCA\SakuraAlbum\AppInfo\Application;
use OCA\SakuraAlbum\Service\ManagedAlbumDownloadService;
use OCA\SakuraAlbum\Service\ManagedAlbumDeletionService;
use OCA\SakuraAlbum\Service\SyncSafetyException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\ZipResponse;
use OCP\IRequest;

class ManagedAlbumController extends Controller {
	public function __construct(
		IRequest $request,
		private readonly string $userId,
		private readonly ManagedAlbumDeletionService $managedAlbumDeletionService,
		private readonly ManagedAlbumDownloadService $managedAlbumDownloadService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function index(): JSONResponse {
		return new JSONResponse($this->managedAlbumDeletionService->managedAlbums(
			$this->userId,
			$this->intParam('limit', 200, 1, 500),
		));
	}

	#[NoAdminRequired]
	public function dryRunDelete(): JSONResponse {
		try {
			return new JSONResponse($this->managedAlbumDeletionService->dryRunDelete(
				$this->userId,
				$this->albumIdsFromRequest(),
				$this->deleteAllFromRequest(),
			));
		} catch (SyncSafetyException $e) {
			return $this->safetyResponse($e);
		} catch (\Throwable) {
			return new JSONResponse([
				'error' => 'managed_album_delete_dry_run_failed',
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	#[NoAdminRequired]
	public function dryRunDeleteStale(): JSONResponse {
		try {
			return new JSONResponse($this->managedAlbumDeletionService->dryRunDeleteStale($this->userId));
		} catch (SyncSafetyException $e) {
			return $this->safetyResponse($e);
		} catch (\Throwable) {
			return new JSONResponse([
				'error' => 'managed_album_stale_delete_dry_run_failed',
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	#[NoAdminRequired]
	public function prepareDownload(): JSONResponse {
		try {
			return new JSONResponse($this->managedAlbumDownloadService->prepare(
				$this->userId,
				$this->intParam('managedId', 0, 1, PHP_INT_MAX),
			));
		} catch (SyncSafetyException $e) {
			return $this->safetyResponse($e);
		} catch (\Throwable) {
			return new JSONResponse([
				'error' => 'managed_album_download_prepare_failed',
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	#[NoAdminRequired]
	public function download(): Response {
		try {
			$plan = $this->managedAlbumDownloadService->zipPlan(
				$this->userId,
				$this->intParam('managedId', 0, 1, PHP_INT_MAX),
			);
			$response = new ZipResponse($this->request, (string)$plan['name']);
			foreach ($plan['files'] as $entry) {
				$file = $entry['file'];
				$handle = $file->fopen('rb');
				if (!is_resource($handle)) {
					continue;
				}
				$response->addResource($handle, (string)$entry['internalName'], (int)$file->getSize(), (int)$file->getMTime());
			}

			return $response;
		} catch (SyncSafetyException $e) {
			return $this->safetyResponse($e);
		} catch (\Throwable) {
			return new JSONResponse([
				'error' => 'managed_album_download_failed',
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	#[NoAdminRequired]
	public function delete(): JSONResponse {
		try {
			return new JSONResponse($this->managedAlbumDeletionService->delete(
				$this->userId,
				$this->albumIdsFromRequest(),
				$this->deleteAllFromRequest(),
				$this->stringParam('confirmation'),
				$this->stringParam('planFingerprint'),
			));
		} catch (SyncSafetyException $e) {
			return $this->safetyResponse($e);
		} catch (\Throwable) {
			return new JSONResponse([
				'error' => 'managed_album_delete_failed',
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	private function albumIdsFromRequest(): array {
		$albumIds = $this->request->getParam('albumIds', []);
		return is_array($albumIds) ? $albumIds : [];
	}

	private function deleteAllFromRequest(): bool {
		$deleteAll = $this->request->getParam('deleteAll', false);
		if (is_bool($deleteAll)) {
			return $deleteAll;
		}
		if (is_string($deleteAll)) {
			return in_array(strtolower($deleteAll), ['1', 'true', 'yes', 'on'], true);
		}
		if (!is_scalar($deleteAll)) {
			return false;
		}

		return (int)$deleteAll === 1;
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
