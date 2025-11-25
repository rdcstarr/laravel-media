<?php

namespace Rdcstarr\Media\Commands;

use Artisan;
use Exception;
use Illuminate\Console\Command;
use function Laravel\Prompts\confirm;

class InstallMediaCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	public $signature = 'media:install
		{--force : Force the installation without confirmation}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	public $description = 'Install the media package';

	/**
	 * Execute the console command.
	 */
	public function handle()
	{
		if (!$this->option('force'))
		{
			if (!confirm('This will publish and run the migrations. Do you want to continue?'))
			{
				$this->components->warn('Installation cancelled.');
				return;
			}
		}

		$this->components->info('Starting Media Package Installation...');

		$steps = [
			'📄 Publishing migrations' => 'publishMigrations',
			'🏁 Running migrations'    => 'runMigrations',
		];

		foreach ($steps as $name => $method)
		{
			try
			{
				$this->components->task($name, fn() => $this->{$method}());
			}
			catch (Exception $e)
			{
				$this->components->error($name . ' failed: ' . $e->getMessage());
				return;
			}
		}

		$this->components->success('Media Package Installation Completed Successfully!');
	}

	/**
	 * Publish the migrations.
	 */
	protected function publishMigrations(): void
	{
		Artisan::call('vendor:publish', [
			'--provider' => 'Rdcstarr\Media\MediaServiceProvider',
			'--tag'      => 'laravel-media-migrations',
		]);
	}

	/**
	 * Run the migrations.
	 */
	protected function runMigrations(): void
	{
		Artisan::call('migrate');
	}
}
