<?php

namespace Rdcstarr\LaravelMedia\Enums;

enum ImageExtension: string
{
	case JPEG = 'jpeg';
	case JPG = 'jpg';
	case PNG = 'png';
	case WEBP = 'webp';
	case AVIF = 'avif';
	case GIF = 'gif';
	case SVG = 'svg';
	case BMP = 'bmp';
	case TIFF = 'tiff';

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
	 * Check if a string is a valid image extension.
	 *
	 * @param string $extension
	 * @return bool
	 */
	public static function isValid(string $extension): bool
	{
		return self::fromString($extension) !== null;
	}
}
