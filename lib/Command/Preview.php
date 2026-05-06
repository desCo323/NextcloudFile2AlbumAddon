<?php

declare(strict_types=1);

namespace OCA\SakuraAlbum\Command;

use OCA\SakuraAlbum\Service\AlbumPlanService;
use OCA\SakuraAlbum\Service\SettingsService;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Preview extends Command {
	public function __construct(
		private readonly IUserManager $userManager,
		private readonly SettingsService $settingsService,
		private readonly AlbumPlanService $albumPlanService,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('sakuraalbum:preview')
			->setDescription('Preview SakuraAlbum album planning for a user without changing Photos albums')
			->addOption('user', null, InputOption::VALUE_REQUIRED, 'Nextcloud user id to preview')
			->addOption('json', null, InputOption::VALUE_NONE, 'Print machine-readable JSON');
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$io = new SymfonyStyle($input, $output);
		$userId = $this->userId($input);
		if ($userId === '' || !$this->userManager->userExists($userId)) {
			return $this->error($io, $output, $input, 'unknown_user', 'A valid --user value is required.');
		}

		try {
			$settings = $this->settingsService->getEffectiveUserSettings($userId);
			$preview = $this->albumPlanService->buildPreview($userId, $settings, $this->settingsService->getPreviewLimits());
			$result = [
				'userId' => $userId,
				'effectiveSettings' => $settings,
				'preview' => $preview,
			];
			if ($input->getOption('json') === true) {
				$this->writeJson($output, $result);
				return Command::SUCCESS;
			}

			$summary = $preview['summary'] ?? [];
			$io->title('SakuraAlbum preview');
			$io->definitionList(
				['User' => $userId],
				['Enabled' => ($settings['enabled'] ?? false) ? 'yes' : 'no'],
				['Planned albums' => (string)($summary['plannedAlbums'] ?? 0)],
				['Planned links' => (string)($summary['plannedLinks'] ?? 0)],
				['Media files' => (string)($summary['mediaFiles'] ?? 0)],
				['Warnings' => (string)count($preview['warnings'] ?? [])],
			);
			$this->writeAlbumSample($io, $preview['albums'] ?? []);
			return Command::SUCCESS;
		} catch (\Throwable $e) {
			return $this->error($io, $output, $input, 'preview_failed', $e->getMessage());
		}
	}

	private function userId(InputInterface $input): string {
		$value = $input->getOption('user');
		return is_scalar($value) ? trim((string)$value) : '';
	}

	private function writeAlbumSample(SymfonyStyle $io, array $albums): void {
		if ($albums === []) {
			$io->note('No planned albums in the preview.');
			return;
		}

		$rows = [];
		foreach (array_slice($albums, 0, 20) as $album) {
			$rows[] = [
				(string)($album['sourceRoot'] ?? ''),
				(string)($album['albumName'] ?? ''),
				(string)($album['targetPath'] ?? ''),
				(string)($album['mediaCount'] ?? 0),
			];
		}
		$io->table(['Source', 'Album', 'Target', 'Media'], $rows);
		if (count($albums) > 20) {
			$io->note('Only the first 20 planned albums are shown. Use --json for the full result.');
		}
	}

	private function error(SymfonyStyle $io, OutputInterface $output, InputInterface $input, string $code, string $message): int {
		if ($input->getOption('json') === true) {
			$this->writeJson($output, [
				'error' => $code,
				'message' => $message,
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
