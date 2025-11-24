<?php

namespace Rdcstarr\Media\Enums;

enum AudioExtension: string
{
	case MP3 = 'mp3';
	case WAV = 'wav';
	case OGG = 'ogg';
	case M4A = 'm4a';
	case AAC = 'aac';
	case FLAC = 'flac';
	case WMA = 'wma';
	case OPUS = 'opus';

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
	 * Check if a string is a valid audio extension.
	 *
	 * @param string $extension
	 * @return bool
	 */
	public static function isValid(string $extension): bool
	{
		return self::fromString($extension) !== null;
	}
}
