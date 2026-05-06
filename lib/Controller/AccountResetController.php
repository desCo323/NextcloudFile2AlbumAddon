<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Controller;

use OCA\SakuraAlbum\AppInfo\Application;
use OCA\SakuraAlbum\Service\AccountResetService;
use OCA\SakuraAlbum\Service\LogService;
use OCA\SakuraAlbum\Service\SyncSafetyException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class AccountResetController extends Controller {
	public function __construct(
		IRequest $request,
		private readonly string $userId,
		private readonly AccountResetService $accountResetService,
		private readonly LogService $logService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function dryRun(): JSONResponse {
		try {
			return new JSONResponse($this->accountResetService->dryRun($this->userId));
		} catch (SyncSafetyException $e) {
			return $this->safetyResponse($e);
		} catch (\Throwable $e) {
			$this->logService->exception('account_reset_dry_run_failed', $e, $this->userId);
			return new JSONResponse([
				'error' => 'account_reset_dry_run_failed',
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	#[NoAdminRequired]
	public function reset(): JSONResponse {
		try {
			return new JSONResponse($this->accountResetService->reset(
				$this->userId,
				$this->stringParam('confirmation'),
				$this->stringParam('planFingerprint'),
				$this->stringParam('deletePlanFingerprint'),
			));
		} catch (SyncSafetyException $e) {
			return $this->safetyResponse($e);
		} catch (\Throwable $e) {
			$this->logService->exception('account_reset_failed', $e, $this->userId);
			return new JSONResponse([
				'error' => 'account_reset_failed',
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
}
