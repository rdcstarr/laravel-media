<?php

namespace Rdcstarr\Media\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Rdcstarr\Media\Models\Media;

class CleanupMediaCommand extends Command
{
	public $signature = 'laravel-media:cleanup
		{--dry-run : Show what would be deleted without actually deleting}
		{--orphaned : Clean orphaned media records (models deleted)}
		{--missing : Clean media records with missing files}
		{--unused : Clean files in storage without media records}
		{--all : Run all cleanup operations}';

	public $description = 'Clean up orphaned media files and records';

	public function handle()
	{
		$dryRun = $this->option('dry-run');
		$runAll = $this->option('all');

		if ($dryRun)
		{
			$this->warn('🔍 DRY RUN MODE - No changes will be made');
			$this->newLine();
		}

		$operations = 0;

		if ($this->option('orphaned') || $runAll)
		{
			$operations++;
			$this->cleanOrphanedRecords($dryRun);
		}

		if ($this->option('missing') || $runAll)
		{
			$operations++;
			$this->cleanMissingFiles($dryRun);
		}

		if ($this->option('unused') || $runAll)
		{
			$operations++;
			$this->cleanUnusedFiles($dryRun);
		}

		if ($operations === 0)
		{
			$this->error('❌ No cleanup operation specified. Use --orphaned, --missing, --unused, or --all');
			$this->newLine();
			$this->info('💡 Use --help to see all available options');
		}

		$this->newLine();
		$this->info('✅ Cleanup completed successfully!');
	}

	protected function cleanOrphanedRecords(bool $dryRun): void
	{
		$this->info('🔍 Checking for orphaned media records...');

		$orphanedCount = 0;
		$deletedSize   = 0;

		Media::chunk(100, function ($mediaItems) use ($dryRun, &$orphanedCount, &$deletedSize)
		{
			foreach ($mediaItems as $media)
			{
				$exists = DB::table($media->mediable_type::make()->getTable())
					->where('id', $media->mediable_id)
					->exists();

				if (!$exists)
				{
					$orphanedCount++;
					$deletedSize += $media->size ?? 0;

					$this->line("  ⚠️  Orphaned: {$media->collection}/{$media->path} (ID: {$media->id})");

					if (!$dryRun)
					{
						$media->delete();
					}
				}
			}
		});

		if ($orphanedCount > 0)
		{
			$sizeFormatted = $this->formatBytes($deletedSize);
			$action        = $dryRun ? 'Would delete' : 'Deleted';
			$this->warn("  {$action} {$orphanedCount} orphaned record(s) (~{$sizeFormatted})");
		}
		else
		{
			$this->info('  ✓ No orphaned records found');
		}

		$this->newLine();
	}

	protected function cleanMissingFiles(bool $dryRun): void
	{
		$this->info('🔍 Checking for media records with missing files...');

		$missingCount = 0;
		$deletedSize  = 0;

		Media::chunk(100, function ($mediaItems) use ($dryRun, &$missingCount, &$deletedSize)
		{
			foreach ($mediaItems as $media)
			{
				try
				{
					$disk = Storage::disk($media->disk);
					$path = ltrim($media->path, '/');

					if (!$disk->exists($path))
					{
						$missingCount++;
						$deletedSize += $media->size ?? 0;

						$this->line("  ⚠️  Missing file: {$media->disk}://{$media->path} (ID: {$media->id})");

						if (!$dryRun)
						{
							$media->withoutEvents(function () use ($media)
							{
								$media->forceDelete();
							});
						}
					}
				}
				catch (Exception $e)
				{
					$this->error("  ❌ Error checking {$media->disk}://{$media->path}: {$e->getMessage()}");
				}
			}
		});

		if ($missingCount > 0)
		{
			$sizeFormatted = $this->formatBytes($deletedSize);
			$action        = $dryRun ? 'Would delete' : 'Deleted';
			$this->warn("  {$action} {$missingCount} record(s) with missing files (~{$sizeFormatted})");
		}
		else
		{
			$this->info('  ✓ All media files exist');
		}

		$this->newLine();
	}

	protected function cleanUnusedFiles(bool $dryRun): void
	{
		$this->info('🔍 Checking for files without media records...');

		$disks       = Media::select('disk')->distinct()->pluck('disk');
		$unusedCount = 0;
		$deletedSize = 0;

		foreach ($disks as $diskName)
		{
			try
			{
				$disk = Storage::disk($diskName);
				$this->line("  Scanning disk: {$diskName}");

				$mediaPaths = Media::where('disk', $diskName)
					->pluck('path')
					->map(fn($path) => ltrim($path, '/'))
					->toArray();

				$directories = $this->getMediaDirectories($disk);

				foreach ($directories as $directory)
				{
					if (!$disk->exists($directory))
					{
						continue;
					}

					$files = $disk->allFiles($directory);

					foreach ($files as $file)
					{
						if (!in_array($file, $mediaPaths))
						{
							$unusedCount++;
							$size         = $disk->size($file);
							$deletedSize += $size;

							$this->line("    ⚠️  Unused: {$diskName}://{$file}");

							if (!$dryRun)
							{
								$disk->delete($file);
							}
						}
					}
				}
			}
			catch (Exception $e)
			{
				$this->error("  ❌ Error scanning disk {$diskName}: {$e->getMessage()}");
			}
		}

		if ($unusedCount > 0)
		{
			$sizeFormatted = $this->formatBytes($deletedSize);
			$action        = $dryRun ? 'Would delete' : 'Deleted';
			$this->warn("  {$action} {$unusedCount} unused file(s) (~{$sizeFormatted})");
		}
		else
		{
			$this->info('  ✓ No unused files found');
		}

		$this->newLine();
	}

	protected function getMediaDirectories($disk): array
	{
		$directories = Media::select(DB::raw('DISTINCT SUBSTRING_INDEX(path, "/", -2) as base_path'))
			->get()
			->map(function ($item)
			{
				$parts = explode('/', $item->base_path);
				array_pop($parts);
				return implode('/', $parts);
			})
			->filter()
			->unique()
			->values()
			->toArray();

		if (empty($directories))
		{
			return ['media', 'uploads', 'public'];
		}

		return $directories;
	}

	protected function formatBytes(int $bytes, int $precision = 2): string
	{
		$units = ['B', 'KB', 'MB', 'GB', 'TB'];

		for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++)
		{
			$bytes /= 1024;
		}

		return round($bytes, $precision) . ' ' . $units[$i];
	}
}
