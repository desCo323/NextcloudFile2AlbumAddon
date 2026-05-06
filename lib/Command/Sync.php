<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Command;

use OCA\SakuraAlbum\Service\AlbumSyncService;
use OCA\SakuraAlbum\Service\SyncSafetyException;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Sync extends Command {
	public function __construct(
		private readonly IUserManager $userManager,
		private readonly AlbumSyncService $albumSyncService,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('sakuraalbum:sync')
			->setDescription('Run a SakuraAlbum sync dry-run for a user')
			->addOption('user', null, InputOption::VALUE_REQUIRED, 'Nextcloud user id to inspect')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Required safety flag; this command does not write Photos albums')
			->addOption('json', null, InputOption::VALUE_NONE, 'Print machine-readable JSON');
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$io = new SymfonyStyle($input, $output);
		$userId = $this->userId($input);
		if ($userId === '' || !$this->userManager->userExists($userId)) {
			return $this->error($io, $output, $input, 'unknown_user', 'A valid --user value is required.');
		}
		if ($input->getOption('dry-run') !== true) {
			return $this->error($io, $output, $input, 'dry_run_required', 'This command is intentionally dry-run only. Add --dry-run.');
		}

		try {
			$result = $this->albumSyncService->dryRun($userId);
			if ($input->getOption('json') === true) {
				$this->writeJson($output, $result);
				return Command::SUCCESS;
			}

			$summary = $result['summary'] ?? [];
			$io->title('SakuraAlbum sync dry-run');
			$io->definitionList(
				['User' => $userId],
				['Status' => (string)($result['status'] ?? '')],
				['Can write' => ($result['canWrite'] ?? false) ? 'yes' : 'no'],
				['Planned albums' => (string)($summary['plannedAlbums'] ?? 0)],
				['Planned links' => (string)($summary['plannedLinks'] ?? 0)],
				['Safety issues' => (string)count($result['writeBlockedReasons'] ?? [])],
				['Plan fingerprint' => (string)($result['planFingerprint'] ?? '')],
			);
			$this->writeIssues($io, $result['writeBlockedReasons'] ?? []);
			return ($result['canWrite'] ?? false) ? Command::SUCCESS : 2;
		} catch (SyncSafetyException $e) {
			return $this->error($io, $output, $input, $e->getErrorCode(), $e->getMessage(), $e->getDetails());
		} catch (\Throwable $e) {
			return $this->error($io, $output, $input, 'sync_dry_run_failed', $e->getMessage());
		}
	}

	private function userId(InputInterface $input): string {
		$value = $input->getOption('user');
		return is_scalar($value) ? trim((string)$value) : '';
	}

	private function writeIssues(SymfonyStyle $io, array $issues): void {
		if ($issues === []) {
			return;
		}

		$rows = [];
		foreach ($issues as $issue) {
			$rows[] = [
				(string)($issue['code'] ?? ''),
				json_encode($issue, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
			];
		}
		$io->table(['Issue', 'Details'], $rows);
	}

	private function error(SymfonyStyle $io, OutputInterface $output, InputInterface $input, string $code, string $message, array $details = []): int {
		if ($input->getOption('json') === true) {
			$this->writeJson($output, [
				'error' => $code,
				'message' => $message,
				'details' => $details,
			]);
		} else {
			$io->error($message);
		}

		return Command::FAILURE;
	}

	private function writeJson(OutputInterface $output, array $data): void {
		$output->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
	}
}
