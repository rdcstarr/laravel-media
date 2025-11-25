<?php

namespace Rdcstarr\Media;

use Rdcstarr\Media\Commands\CleanupMediaCommand;
use Rdcstarr\Media\Commands\InstallMediaCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MediaServiceProvider extends PackageServiceProvider
{
	public function configurePackage(Package $package): void
	{
		$package
			->name('laravel-media')
			->hasMigration('create_media_table')
			->hasCommands([
				InstallMediaCommand::class,
				CleanupMediaCommand::class,
			]);
	}
}
