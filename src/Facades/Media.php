<?php

namespace Rdcstarr\Media\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Rdcstarr\Media\MediaService
 */
class Media extends Facade
{
	protected static function getFacadeAccessor(): string
	{
		return \Rdcstarr\Media\MediaService::class;
	}
}
