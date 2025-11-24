<?php

namespace Rdcstarr\Media\Enums;

enum VideoExtension: string
{
	case MP4 = 'mp4';
	case WEBM = 'webm';
	case OGG = 'ogg';
	case AVI = 'avi';
	case MOV = 'mov';
	case WMV = 'wmv';
	case FLV = 'flv';
	case MKV = 'mkv';
	case M4V = 'm4v';

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

		$normalized = strtolower(ltrim($value, '.'));

		return self::tryFrom($normalized);
	}

	/**
	 * Check if a string is a valid video extension.
	 *
	 * @param string $extension
	 * @return bool
	 */
	public static function isValid(string $extension): bool
	{
		return self::fromString($extension) !== null;
	}
}
