<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Controller;

use OCA\SakuraAlbum\AppInfo\Application;
use OCA\SakuraAlbum\Service\DiagnosticCsvExportService;
use OCA\SakuraAlbum\Service\DiagnosticReportService;
use OCA\SakuraAlbum\Service\LogService;
use OCA\SakuraAlbum\Settings\Admin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;

class DiagnosticsController extends Controller {
	public function __construct(
		IRequest $request,
		private readonly string $userId,
		private readonly DiagnosticReportService $diagnosticReportService,
		private readonly DiagnosticCsvExportService $diagnosticCsvExportService,
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
					'healthStatus' => $report['health']['status'] ?? 'unknown',
					'healthIssues' => $report['health']['summary']['issueCount'] ?? 0,
				],
			], 'User diagnostic report was prepared.');
			$this->logHealthIssues('diagnostic_health_issues_detected', $this->userId, $report['health'] ?? []);

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

	#[NoAdminRequired]
	public function personalLogsCsv(): Response {
		try {
			$export = $this->diagnosticCsvExportService->userCsv(
				$this->userId,
				$this->intParam('limit', 100, 1, 500),
			);
			$this->logService->success('diagnostic_csv_export_created', $this->userId, [
				'summary' => [
					'scope' => 'user',
					'filename' => $export['filename'],
					'bytes' => $export['bytes'],
					'storage' => $export['storage'],
				],
			], 'User diagnostic CSV export was created.');

			return new DataDownloadResponse(
				$export['csv'],
				$export['filename'],
				'text/csv; charset=utf-8',
			);
		} catch (\Throwable $e) {
			$this->logService->exception('diagnostic_csv_export_failed', $e, $this->userId);

			return new JSONResponse([
				'error' => 'diagnostic_csv_export_failed',
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
					'healthStatus' => $report['health']['status'] ?? 'unknown',
					'healthIssues' => $report['health']['summary']['issueCount'] ?? 0,
				],
			], 'Admin diagnostic report was prepared.');
			$this->logHealthIssues('admin_diagnostic_health_issues_detected', null, $report['health'] ?? []);

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

	#[AuthorizedAdminSetting(settings: Admin::class)]
	public function adminLogsCsv(): Response {
		try {
			$userId = $this->request->getParam('userId', null);
			$targetUserId = is_string($userId) && trim($userId) !== '' ? trim($userId) : null;
			$export = $this->diagnosticCsvExportService->adminCsv(
				$targetUserId,
				$this->intParam('limit', 200, 1, 500),
			);
			$this->logService->success('admin_diagnostic_csv_export_created', null, [
				'summary' => [
					'scope' => 'admin',
					'userId' => $targetUserId,
					'filename' => $export['filename'],
					'bytes' => $export['bytes'],
					'storage' => $export['storage'],
				],
			], 'Admin diagnostic CSV export was created.');

			return new DataDownloadResponse(
				$export['csv'],
				$export['filename'],
				'text/csv; charset=utf-8',
			);
		} catch (\Throwable $e) {
			$this->logService->exception('admin_diagnostic_csv_export_failed', $e);

			return new JSONResponse([
				'error' => 'admin_diagnostic_csv_export_failed',
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	private function intParam(string $key, int $default, int $min, int $max): int {
		$value = $this->request->getParam($key, $default);
		$value = is_numeric($value) ? (int)$value : $default;
		return max($min, min($max, $value));
	}

	private function logHealthIssues(string $event, ?string $userId, array $health): void {
		$issues = $health['issues'] ?? [];
		if (!is_array($issues) || $issues === []) {
			return;
		}
		$summary = $health['summary'] ?? [];
		$critical = (int)($summary['criticalCount'] ?? 0);
		$context = [
			'summary' => [
				'status' => $health['status'] ?? 'unknown',
				'issueCount' => count($issues),
				'criticalCount' => $critical,
				'warningCount' => (int)($summary['warningCount'] ?? 0),
				'codes' => array_values(array_map(
					static fn (array $issue): string => (string)($issue['code'] ?? 'unknown'),
					array_slice($issues, 0, 12),
				)),
			],
		];
		if ($critical > 0) {
			$this->logService->error($event, $userId, $context, 'Operational health check found critical SakuraAlbum issues.');
			return;
		}
		$this->logService->warning($event, $userId, $context, 'Operational health check found SakuraAlbum warnings.');
	}
}
