<?php

namespace Rdcstarr\LaravelMedia\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Rdcstarr\LaravelMedia\LaravelMedia
 *
 * @method static \Rdcstarr\LaravelMedia\LaravelMedia __construct(\Illuminate\Database\Eloquent\Model $model)
 * @method static self file(mixed $file)
 * @method static \Illuminate\Support\Collection addToCollection(string $collection, string $name = '', string $path = '', array $metadata = [])
 * @method static self replaceExisting(bool $state = true)
 * @method static self keepOriginalFileName(bool $state = true)
 */
class LaravelMedia extends Facade
{
	protected static function getFacadeAccessor(): string
	{
		return \Rdcstarr\LaravelMedia\LaravelMedia::class;
	}
}
