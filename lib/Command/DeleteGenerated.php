<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Command;

use OCA\SakuraAlbum\Service\ManagedAlbumDeletionService;
use OCA\SakuraAlbum\Service\SyncSafetyException;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DeleteGenerated extends Command {
	public function __construct(
		private readonly IUserManager $userManager,
		private readonly ManagedAlbumDeletionService $managedAlbumDeletionService,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('sakuraalbum:delete-generated')
			->setDescription('Dry-run deletion of SakuraAlbum-managed Photos albums for a user')
			->addOption('user', null, InputOption::VALUE_REQUIRED, 'Nextcloud user id to inspect')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'Required safety flag; this command does not delete Photos albums')
			->addOption('all', null, InputOption::VALUE_NONE, 'Preview all active SakuraAlbum-managed albums within the admin album limit')
			->addOption('album-id', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Specific SakuraAlbum managed album id to preview')
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

		$deleteAll = $input->getOption('all') === true;
		$albumIds = $this->albumIds($input);
		if (!$deleteAll && $albumIds === []) {
			return $this->error($io, $output, $input, 'selection_required', 'Use --all or at least one --album-id value.');
		}

		try {
			$result = $this->managedAlbumDeletionService->dryRunDelete($userId, $albumIds, $deleteAll);
			if ($input->getOption('json') === true) {
				$this->writeJson($output, $result);
				return Command::SUCCESS;
			}

			$summary = $result['summary'] ?? [];
			$io->title('SakuraAlbum managed delete dry-run');
			$io->definitionList(
				['User' => $userId],
				['Status' => (string)($result['status'] ?? '')],
				['Can delete' => ($result['canDelete'] ?? false) ? 'yes' : 'no'],
				['Planned albums' => (string)($summary['plannedAlbums'] ?? 0)],
				['Would delete Photos albums' => (string)($summary['wouldDeletePhotosAlbums'] ?? 0)],
				['Would clean tracking records' => (string)($summary['wouldCleanupTrackingRecords'] ?? 0)],
				['Safety issues' => (string)count($result['deleteBlockedReasons'] ?? [])],
				['Plan fingerprint' => (string)($result['planFingerprint'] ?? '')],
			);
			$this->writeAlbumSample($io, $result['albums'] ?? []);
			return ($result['canDelete'] ?? false) ? Command::SUCCESS : 2;
		} catch (SyncSafetyException $e) {
			return $this->error($io, $output, $input, $e->getErrorCode(), $e->getMessage(), $e->getDetails());
		} catch (\Throwable $e) {
			return $this->error($io, $output, $input, 'delete_dry_run_failed', $e->getMessage());
		}
	}

	private function userId(InputInterface $input): string {
		$value = $input->getOption('user');
		return is_scalar($value) ? trim((string)$value) : '';
	}

	private function albumIds(InputInterface $input): array {
		$values = $input->getOption('album-id');
		if (!is_array($values)) {
			return [];
		}

		$ids = [];
		foreach ($values as $value) {
			if (!is_numeric($value)) {
				continue;
			}
			$id = (int)$value;
			if ($id > 0) {
				$ids[] = $id;
			}
		}

		return array_values(array_unique($ids));
	}

	private function writeAlbumSample(SymfonyStyle $io, array $albums): void {
		if ($albums === []) {
			$io->note('No managed albums matched this delete dry-run.');
			return;
		}

		$rows = [];
		foreach (array_slice($albums, 0, 20) as $album) {
			$rows[] = [
				(string)($album['managedId'] ?? ''),
				(string)($album['albumName'] ?? ''),
				(string)($album['deleteAction'] ?? ''),
				(string)($album['blockReason'] ?? ''),
			];
		}
		$io->table(['Managed id', 'Album', 'Action', 'Block reason'], $rows);
		if (count($albums) > 20) {
			$io->note('Only the first 20 managed albums are shown. Use --json for the full result.');
		}
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
