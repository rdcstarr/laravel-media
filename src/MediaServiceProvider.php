<?php

namespace Rdcstarr\Media;

use Rdcstarr\Media\Commands\CleanupMediaCommand;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MediaServiceProvider extends PackageServiceProvider
{
	/*
	 * This class is a Package Service Provider
	 *
	 * More info: https://github.com/spatie/laravel-package-tools
	 */
	public function configurePackage(Package $package): void
	{
		$package
			->name('laravel-media')
			->discoversMigrations()
			->runsMigrations()
			->hasCommands([
				CleanupMediaCommand::class,
			])
			->hasInstallCommand(function (InstallCommand $command)
			{
				$command
					->publishMigrations()
					->askToRunMigrations();
			});
	}
}
