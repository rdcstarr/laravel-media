<?php

namespace Rdcstarr\Media\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Rdcstarr\Media\MediaService;
use Rdcstarr\Media\Models\Media;

trait HasMedia
{
	/**
	 * Create a new MediaService instance for this model.
	 *
	 * @return MediaService
	 */
	public function attachMedia(): MediaService
	{
		return new MediaService($this);
	}

	/**
	 * Handle dynamic method calls for media operations.
	 *
	 * @param string $method
	 * @param array $parameters
	 * @return mixed
	 */
	public function __call($method, $parameters)
	{
		if ($match = Str::match('/^getMedia(.+)Collection$/', $method))
		{
			$collection = Str::snake($match);

			return $this->getMediaCollection($collection);
		}

		if ($match = Str::match('/^getMedia(.+)Url$/', $method))
		{
			$collection = Str::snake($match);
			$extension  = $parameters[0] ?? null;
			$default    = $parameters[1] ?? null;

			return $this->getMediaUrl($collection, $extension, $default);
		}
		if ($match = Str::match('/^getMedia(.+)Size$/', $method))
		{
			$collection = Str::snake($match);
			$extension  = $parameters[0] ?? null;

			return $this->getMediaSize($collection, $extension);
		}

		if ($match = Str::match('/^getMedia(.+)MetaData$/', $method))
		{
			$collection = Str::snake($match);
			$extension  = $parameters[0] ?? null;

			return $this->getMediaMetaData($collection, $extension);
		}

		return parent::__call($method, $parameters);
	}

	/**
	 * Get all media for this model.
	 *
	 * @return MorphMany
	 */
	public function media(): MorphMany
	{
		return $this->morphMany(Media::class, 'mediable');
	}

	/**
	 * Get all media items from a specific collection, keyed by extension.
	 *
	 * @param string $collection
	 * @return \Illuminate\Support\Collection
	 */
	public function getMediaCollection(string $collection)
	{
		if ($this->relationLoaded('media'))
		{
			return $this->media->where('collection', $collection)->keyBy('extension');
		}

		return $this->media()->where('collection', $collection)->get()->keyBy('extension');
	}

	/**
	 * Get the URL of a media item from a collection.
	 *
	 * @param string $collection
	 * @param string|null $extension
	 * @param string|null $default
	 * @return string|null
	 */
	public function getMediaUrl(string $collection, ?string $extension = null, ?string $default = null)
	{
		return $this->findMedia($collection, $extension)?->url ?? $default;
	}

	/**
	 * Get the size of a media item from a collection.
	 *
	 * @param string $collection
	 * @param string|null $extension
	 * @return int|null
	 */
	public function getMediaSize(string $collection, ?string $extension = null)
	{
		return $this->findMedia($collection, $extension)?->size;
	}

	/**
	 * Get the metadata of a media item from a collection.
	 *
	 * @param string $collection
	 * @param string|null $extension
	 * @return array|null
	 */
	public function getMediaMetaData(string $collection, ?string $extension = null)
	{
		return $this->findMedia($collection, $extension)?->metadata;
	}

	/**
	 * Check if the model has media in a specific collection.
	 *
	 * @param string $collection
	 * @param string|null $extension
	 * @return bool
	 */
	public function hasMedia(string $collection, ?string $extension = null): bool
	{
		return $this->findMedia($collection, $extension) !== null;
	}

	/**
	 * Clear all media from a collection, optionally filtered by extensions.
	 *
	 * @param string $collection
	 * @param array|null $extensions
	 * @return void
	 */
	public function clearMediaCollection(string $collection, ?array $extensions = null): void
	{
		$this->loadMissing('media');

		$mediaItems = $this->media->where('collection', $collection);

		if (!empty($extensions))
		{
			$mediaItems = $mediaItems->whereIn('extension', $extensions);
		}

		$mediaItems->each(fn(Media $media) => $media->delete());
	}

	/**
	 * Find a specific media item in a collection.
	 *
	 * @param string $collection
	 * @param string|null $extension
	 * @return Media|null
	 */
	protected function findMedia(string $collection, ?string $extension = null): ?Media
	{
		if ($this->relationLoaded('media'))
		{
			$media = $this->media->where('collection', $collection);

			if ($extension !== null)
			{
				return $media->firstWhere('extension', $extension);
			}

			return $media->sortByDesc('updated_at')->first();
		}

		$query = $this->media()->where('collection', $collection);

		if ($extension !== null)
		{
			$query->where('extension', $extension);
		}

		return $query->latest()->first();
	}

	/**
	 * Get custom events for media collections.
	 *
	 * @return array
	 */
	public function mediaCollectionEvents(): array
	{
		return [];
	}

	/**
	 * Dispatch custom event for a media collection if defined.
	 *
	 * @param string $collection
	 * @param string $action 'created', 'updated', or 'deleted'
	 * @param Media|null $media
	 * @return void
	 */
	public function dispatchMediaCollectionEvent(string $collection, string $action, ?Media $media = null): void
	{
		$events = $this->mediaCollectionEvents();

		if (!isset($events[$collection]))
		{
			return;
		}

		$collectionConfig = $events[$collection];

		// Support for simple event class string
		if (is_string($collectionConfig))
		{
			event(new $collectionConfig($this, $media, $action));
			return;
		}

		// Support for action-specific events
		if (is_array($collectionConfig) && isset($collectionConfig[$action]))
		{
			$eventClass = $collectionConfig[$action];

			if (is_string($eventClass))
			{
				event(new $eventClass($this, $media, $action));
			}
			else if (is_callable($eventClass))
			{
				$eventClass($this, $media, $action);
			}
		}
		// Support for callable
		else if (is_callable($collectionConfig))
		{
			$collectionConfig($this, $media, $action);
		}
	}

	/**
	 * Boot the HasMedia trait for the model.
	 */
	protected static function bootHasMedia(): void
	{
		static::deleting(function ($model)
		{
			if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting())
			{
				return;
			}

			$model->media->each(fn($media) => $media->delete());
		});
	}
}
