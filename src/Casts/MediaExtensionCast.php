<?php

namespace Rdcstarr\LaravelMedia\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Rdcstarr\LaravelMedia\Enums\AudioExtension;
use Rdcstarr\LaravelMedia\Enums\ImageExtension;
use Rdcstarr\LaravelMedia\Enums\VideoExtension;

class MediaExtensionCast implements CastsAttributes
{
	/**
	 * Cast the given value.
	 *
	 * @param array<string, mixed> $attributes
	 */
	public function get(Model $model, string $key, mixed $value, array $attributes): string
	{
		return (string) $value;
	}

	/**
	 * Prepare the given value for storage.
	 *
	 * @param array<string, mixed> $attributes
	 */
	public function set(Model $model, string $key, mixed $value, array $attributes): string
	{
		if ($value instanceof ImageExtension || $value instanceof VideoExtension || $value instanceof AudioExtension)
		{
			return $value->value;
		}

		return strtolower(ltrim((string) $value, '.'));
	}
}
