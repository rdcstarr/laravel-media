<?php

namespace Rdcstarr\Media;

use Rdcstarr\Media\Commands\CleanupMediaCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MediaServiceProvider extends PackageServiceProvider
{
	public function configurePackage(Package $package): void
	{
		/*
		 * This class is a Package Service Provider
		 *
		 * More info: https://github.com/spatie/laravel-package-tools
		 */
		$package
			->name('laravel-media')
			->hasMigration('create_laravel_media_table')
			->hasCommand(CleanupMediaCommand::class);
	}
}
