<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Controller;

use OCA\SakuraAlbum\AppInfo\Application;
use OCA\SakuraAlbum\Service\DiagnosticReportService;
use OCA\SakuraAlbum\Service\LogService;
use OCA\SakuraAlbum\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class DiagnosticsController extends Controller {
	public function __construct(
		IRequest $request,
		private readonly string $userId,
		private readonly DiagnosticReportService $diagnosticReportService,
		private readonly LogService $logService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function personalReport(): JSONResponse {
		try {
			$report = $this->diagnosticReportService->userReport(
				$this->userId,
				$this->intParam('limit', 25, 1, 100),
			);
			$this->logService->success('diagnostic_report_prepared', $this->userId, [
				'summary' => [
					'scope' => 'user',
					'limit' => $report['meta']['limit'] ?? 0,
					'logCount' => count($report['logs'] ?? []),
				],
			], 'User diagnostic report was prepared.');

			return new JSONResponse([
				'report' => $report,
			]);
		} catch (\Throwable $e) {
			$this->logService->exception('diagnostic_report_failed', $e, $this->userId);

			return new JSONResponse([
				'error' => 'diagnostic_report_failed',
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function adminReport(): JSONResponse {
		try {
			$userId = $this->request->getParam('userId', null);
			$report = $this->diagnosticReportService->adminReport(
				is_string($userId) ? $userId : null,
				$this->intParam('limit', 50, 1, 200),
			);
			$this->logService->success('admin_diagnostic_report_prepared', null, [
				'summary' => [
					'scope' => 'admin',
					'userId' => $report['meta']['userId'] ?? null,
					'limit' => $report['meta']['limit'] ?? 0,
					'logCount' => count($report['logs'] ?? []),
				],
			], 'Admin diagnostic report was prepared.');

			return new JSONResponse([
				'report' => $report,
			]);
		} catch (\Throwable $e) {
			$this->logService->exception('admin_diagnostic_report_failed', $e);

			return new JSONResponse([
				'error' => 'admin_diagnostic_report_failed',
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	private function intParam(string $key, int $default, int $min, int $max): int {
		$value = $this->request->getParam($key, $default);
		$value = is_numeric($value) ? (int)$value : $default;
		return max($min, min($max, $value));
	}
}
