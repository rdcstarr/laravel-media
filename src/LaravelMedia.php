<?php

namespace Rdcstarr\LaravelMedia;

use Rdcstarr\LaravelMedia\Enums\AudioExtension;
use Rdcstarr\LaravelMedia\Enums\ImageExtension;
use Rdcstarr\LaravelMedia\Enums\MediaType;
use Rdcstarr\LaravelMedia\Enums\VideoExtension;
use Rdcstarr\LaravelMedia\Traits\HasMedia;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

class LaravelMedia
{
	protected Model $model;
	protected mixed $file = null;
	protected array $collections = [];
	protected string $collection;
	protected bool $shouldClearCollection = false;
	protected bool $useOriginalFileName = false;

	/**
	 * Create a new LaravelMedia instance for the given model.
	 *
	 * @param Model $model The model that uses HasMedia trait
	 * @throws InvalidArgumentException
	 */
	public function __construct(Model $model)
	{
		if (!in_array(HasMedia::class, class_uses_recursive($model)))
		{
			throw new InvalidArgumentException(
				get_class($model) . " must use HasMedia trait"
			);
		}

		if (!method_exists($model, 'mediaCollection') || !is_callable([$model, 'mediaCollection']))
		{
			throw new InvalidArgumentException(
				get_class($model) . " must implement mediaCollection() method"
			);
		}

		$this->model = $model;

		$collections = $model->mediaCollection();

		$this->collections = match (true)
		{
			is_array($collections) => $collections,
			$collections instanceof Collection => $collections->toArray(),
			default => throw new InvalidArgumentException('Model::mediaCollection must return an array or a Collection.')
		};
	}

	/**
	 * Set the file to be uploaded.
	 *
	 * @param mixed $file URL string or UploadedFile instance
	 * @return self
	 * @throws InvalidArgumentException
	 */
	public function file(mixed $file): self
	{
		if (is_string($file) && filter_var($file, FILTER_VALIDATE_URL))
		{
			$this->file = $this->urlToUploadedFile($file);
		}
		else if ($file instanceof UploadedFile)
		{
			$this->file = $file;
		}
		else
		{
			throw new InvalidArgumentException('File must be a valid URL or an instance of ' . UploadedFile::class);
		}

		return $this;
	}

	/**
	 * Add the file to a specific collection.
	 *
	 * @param string $collection The collection name
	 * @param string $name Optional custom filename
	 * @param string $path Optional custom path
	 * @param array $metadata Optional metadata
	 * @return Collection
	 * @throws InvalidArgumentException|RuntimeException
	 */
	public function addToCollection(string $collection, string $name = '', string $path = '', array $metadata = []): Collection
	{
		if ($this->file === null)
		{
			throw new RuntimeException('No file has been set. Call file() method before addToCollection().');
		}

		if (!Arr::exists($this->collections, $collection))
		{
			throw new InvalidArgumentException("Collection $collection is not defined in the media config");
		}

		$this->collection = $collection;

		$config = array_merge(
			$this->collections[$collection],
			array_filter(['name' => $name, 'path' => $path], fn($value) => $value !== '')
		);

		if ($this->shouldClearCollection)
		{
			$this->model->clearMediaCollection($collection);
			$this->shouldClearCollection = false;
		}

		$type = $this->resolveMediaType($config['type'] ?? null);

		return match ($type)
		{
			MediaType::Image => $this->processImage($config, $metadata),
			MediaType::Video => $this->processVideo($config, $metadata),
			MediaType::Audio => $this->processAudio($config, $metadata),
			MediaType::File => $this->processFile($config, $metadata),
		};
	}

	/**
	 * Replace existing media in collection when adding new file.
	 *
	 * @param bool $state Whether to replace existing media
	 * @return self
	 */
	public function replaceExisting(bool $state = true): self
	{
		$this->shouldClearCollection = $state;

		return $this;
	}

	/**
	 * Keep the original filename instead of generating new one.
	 *
	 * @param bool $state Whether to keep original filename
	 * @return self
	 */
	public function keepOriginalFileName(bool $state = true): self
	{
		$this->useOriginalFileName = $state;

		return $this;
	}

	/**
	 * Process an image file with optional resizing and format conversion.
	 *
	 * @param array $config Configuration for the image processing
	 * @param array $metadata Optional metadata
	 * @return Collection
	 * @throws InvalidArgumentException|RuntimeException
	 */
	protected function processImage(array $config = [], array $metadata = []): Collection
	{
		$name       = $this->resolveFileName($config['name'] ?? null);
		$path       = $this->resolvePath($config['path'] ?? null);
		$disk       = $config['disk'] ?? 'public';
		$storage    = Storage::disk($disk);
		$width      = is_numeric($config['width'] ?? null) ? (int) $config['width'] : null;
		$height     = is_numeric($config['height'] ?? null) ? (int) $config['height'] : null;
		$fit        = $config['fit'] ?? null;
		$visibility = $config['visibility'] ?? null;

		$extensions = $this->resolveImageExtensions($config);

		$fitEnum = match (true)
		{
			$fit === null => null,
			$fit instanceof Fit => $fit,
			is_string($fit) => Fit::tryFrom(strtolower($fit))
			?? throw new InvalidArgumentException(
				"Invalid fit value '{$fit}'. Allowed values: " .
				collect(Fit::cases())->pluck('value')->join(', ')
			),
			default => throw new InvalidArgumentException(
				'The "fit" option must be a string or an instance of ' . Fit::class
			),
		};

		return collect($extensions)->map(function (int $quality, string $extension) use ($storage, $name, $path, $disk, $width, $height, $fitEnum, $visibility, $metadata)
		{
			$image = Image::load($this->file->getRealPath());

			match (true)
			{
				$width && $height => $image->fit($fitEnum ?? Fit::Contain, $width, $height),
				$width => $image->width($width),
				$height => $image->height($height),
				default => null,
			};

			$relativePath = $this->buildRelativePath($path, $name, $extension);
			$absolutePath = $storage->path($relativePath);

			File::ensureDirectoryExists(dirname($absolutePath));

			$image->quality($quality)->optimize()->save($absolutePath);

			if ($visibility !== null)
			{
				$storage->setVisibility($relativePath, $visibility);
			}

			return $this->persistMediaRecord($extension, $relativePath, $disk, $metadata, $storage);
		});
	}

	/**
	 * Process a video file.
	 *
	 * @param array $config Configuration for the video processing
	 * @param array $metadata Optional metadata
	 * @return Collection
	 */
	protected function processVideo(array $config = [], array $metadata = []): Collection
	{
		return $this->storeBinaryFile($config, $metadata);
	}

	/**
	 * Process an audio file.
	 *
	 * @param array $config Configuration for the audio processing
	 * @param array $metadata Optional metadata
	 * @return Collection
	 */
	protected function processAudio(array $config = [], array $metadata = []): Collection
	{
		return $this->storeBinaryFile($config, $metadata);
	}

	/**
	 * Process a generic file.
	 *
	 * @param array $config Configuration for the file processing
	 * @param array $metadata Optional metadata
	 * @return Collection
	 */
	protected function processFile(array $config = [], array $metadata = []): Collection
	{
		return $this->storeBinaryFile($config, $metadata);
	}

	protected function resolveFileName(?string $pattern = null): string
	{
		if (blank($pattern) && $this->useOriginalFileName && $this->file instanceof UploadedFile)
		{
			$original = pathinfo($this->file->getClientOriginalName(), PATHINFO_FILENAME);
			$slug     = Str::slug((string) $original);

			if (!blank($slug))
			{
				return $slug;
			}
		}

		$pattern = blank($pattern) ? '{ulid}' : $pattern;

		return $this->patternBuilder($pattern);
	}

	protected function resolvePath(?string $pattern = null): string
	{
		$pattern = blank($pattern) ? '{model}/{collection}' : $pattern;

		return $this->patternBuilder($pattern);
	}

	protected function patternBuilder(string $pattern): string
	{
		return str($pattern)
			->trim()
			->ltrim('/')
			->rtrim('/')
			->replace('{ulid}', Str::ulid())
			->replace('{uuid}', Str::uuid())
			->replace('{model}', strtolower(class_basename($this->model)))
			->replace('{collection}', $this->collection)
			->toString();
	}

	protected function resolveImageExtensions(array $config): array
	{
		$extensions = $config['extensions'] ?? null;

		if (is_array($extensions) && !empty($extensions))
		{
			$normalized = [];

			foreach ($extensions as $key => $value)
			{
				if (is_int($key))
				{
					// Numeric key: value is extension
					$ext              = $this->extractExtensionValue($value);
					$normalized[$ext] = 100;
					continue;
				}

				// String/Enum key: key is extension, value is quality
				$ext              = $this->extractExtensionValue($key);
				$normalized[$ext] = is_int($value) ? $value : 100;
			}

			return $normalized;
		}

		$guessed = $this->guessExtension($this->file);

		if ($guessed === null)
		{
			throw new InvalidArgumentException('Could not determine file extension for the uploaded image.');
		}

		return [strtolower($guessed) => 100];
	}

	protected function storeBinaryFile(array $config = [], array $metadata = []): Collection
	{
		$name       = $this->resolveFileName($config['name'] ?? null);
		$path       = $this->resolvePath($config['path'] ?? null);
		$disk       = $config['disk'] ?? 'public';
		$storage    = Storage::disk($disk);
		$visibility = $config['visibility'] ?? null;
		$extensions = $this->normalizeExtensions($config);

		if (empty($extensions))
		{
			throw new InvalidArgumentException('Could not determine file extension for the uploaded file.');
		}

		return collect($extensions)->map(function (string $extension) use ($storage, $name, $path, $disk, $visibility, $metadata)
		{
			$extension    = strtolower(ltrim($extension, '.'));
			$relativePath = $this->buildRelativePath($path, $name, $extension);

			$this->writeStream($storage, $relativePath);

			if ($visibility !== null)
			{
				$storage->setVisibility($relativePath, $visibility);
			}

			return $this->persistMediaRecord($extension, $relativePath, $disk, $metadata, $storage);
		});
	}

	protected function buildRelativePath(string $path, string $name, string $extension): string
	{
		return trim($path . '/' . $name . '.' . $extension, '/');
	}

	protected function persistMediaRecord(string $extension, string $relativePath, string $disk, array $metadata = [], ?Filesystem $storage = null)
	{
		$storage ??= Storage::disk($disk);
		$size      = $storage->exists($relativePath) ? $storage->size($relativePath) : null;

		return $this->model->media()->updateOrCreate(
			[
				'extension'  => $extension,
				'collection' => $this->collection,
			],
			[
				'path'     => $relativePath,
				'disk'     => $disk,
				'size'     => $size,
				'metadata' => empty($metadata) ? null : $metadata,
			]);
	}

	protected function writeStream(Filesystem $storage, string $relativePath): void
	{
		$resource = fopen($this->file->getRealPath(), 'r');

		if ($resource === false)
		{
			throw new RuntimeException('Unable to read uploaded file for storage.');
		}

		$storage->put($relativePath, $resource);

		if (is_resource($resource))
		{
			fclose($resource);
		}
	}

	protected function normalizeExtensions(array $config): array
	{
		if (isset($config['extensions']) && is_array($config['extensions']) && !empty($config['extensions']))
		{
			$normalized = [];

			foreach ($config['extensions'] as $key => $value)
			{
				$ext          = is_int($key) ? $value : $key;
				$normalized[] = $this->extractExtensionValue($ext);
			}

			return array_values(array_unique(array_filter($normalized)));
		}

		if (!empty($config['extension']) && is_string($config['extension']))
		{
			return [strtolower(ltrim($config['extension'], '.'))];
		}

		$guessed = $this->guessExtension($this->file);

		return $guessed ? [strtolower($guessed)] : [];
	}

	/**
	 * Extract extension value from enum or string.
	 *
	 * @param mixed $extension
	 * @return string
	 */
	protected function extractExtensionValue(mixed $extension): string
	{
		if ($extension instanceof ImageExtension || $extension instanceof VideoExtension || $extension instanceof AudioExtension)
		{
			return $extension->value;
		}

		return strtolower(ltrim((string) $extension, '.'));
	}

	protected function guessExtension(UploadedFile $file): ?string
	{
		return strtolower(
			$file->guessExtension()
			?: $file->getClientOriginalExtension()
			?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION)
			?: ($file->extension() ?: '')
		) ?: null;
	}

	protected function detectType(UploadedFile $file): MediaType
	{
		$mimeType = $file->getMimeType();

		return match (true)
		{
			str_starts_with($mimeType, 'image/') => MediaType::Image,
			str_starts_with($mimeType, 'video/') => MediaType::Video,
			str_starts_with($mimeType, 'audio/') => MediaType::Audio,
			default => MediaType::File,
		};
	}

	/**
	 * Resolve media type from config value or detect from file.
	 *
	 * @param string|MediaType|null $type
	 * @return MediaType
	 */
	protected function resolveMediaType(string|MediaType|null $type): MediaType
	{
		if ($type instanceof MediaType)
		{
			return $type;
		}

		if (is_string($type))
		{
			$enum = MediaType::tryFrom($type);

			if ($enum === null)
			{
				throw new InvalidArgumentException(
					"Invalid media type '{$type}'. Allowed values: " .
					collect(MediaType::cases())->pluck('value')->join(', ')
				);
			}

			return $enum;
		}

		return $this->detectType($this->file);
	}

	/**
	 * Convert URL to UploadedFile instance
	 *
	 * @param string $url
	 * @return UploadedFile
	 */
	protected function urlToUploadedFile(string $url): UploadedFile
	{
		$tempPath = tempnam(sys_get_temp_dir(), 'media_');

		try
		{
			Http::timeout(30)->sink($tempPath)->get($url)->throw();

			return new UploadedFile(
				$tempPath,
				basename(parse_url($url, PHP_URL_PATH)) ?: 'file',
				mime_content_type($tempPath) ?: null,
				null,
				true
			);
		}
		catch (Exception $e)
		{
			@unlink($tempPath);
			throw $e;
		}
	}
}
