<?php

namespace Rdcstarr\Media\Enums;

enum MediaType: string
{
	case Image = 'image';
	case Video = 'video';
	case Audio = 'audio';
	case File = 'file';

	/**
	 * Get all enum values as an array.
	 *
	 * @return array<string>
	 */
	public static function values(): array
	{
		return array_column(self::cases(), 'value');
	}

	/**
	 * Try to create an instance from a string value.
	 *
	 * @param string|self $value
	 * @return self|null
	 */
	public static function fromString(string|self $value): ?self
	{
		if ($value instanceof self)
		{
			return $value;
		}

		return self::tryFrom($value);
	}
}
