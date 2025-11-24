<?php

namespace Rdcstarr\LaravelMedia\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Rdcstarr\LaravelMedia\LaravelMedia;
use Rdcstarr\LaravelMedia\Models\Media;

trait HasMedia
{
	/**
	 * Create a new LaravelMedia instance for this model.
	 *
	 * @return LaravelMedia
	 */
	public function attachMedia(): LaravelMedia
	{
		return new LaravelMedia($this);
	}

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

			return $this->getMediaUrl($collection, $extension);
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

	public function media(): MorphMany
	{
		return $this->morphMany(Media::class, 'mediable');
	}

	public function getMediaCollection(string $collection)
	{
		if ($this->relationLoaded('media'))
		{
			return $this->media->where('collection', $collection)->keyBy('extension');
		}

		return $this->media()->where('collection', $collection)->get()->keyBy('extension');
	}

	public function getMediaUrl(string $collection, ?string $extension = null)
	{
		return $this->findMedia($collection, $extension)?->url;
	}

	public function getMediaSize(string $collection, ?string $extension = null)
	{
		return $this->findMedia($collection, $extension)?->size;
	}

	public function getMediaMetaData(string $collection, ?string $extension = null)
	{
		return $this->findMedia($collection, $extension)?->metadata;
	}

	public function hasMedia(string $collection, ?string $extension = null): bool
	{
		return $this->findMedia($collection, $extension) !== null;
	}

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
	 * Boot the HasMedia trait for the model.
	 */
	protected static function bootHasMedia(): void
	{
		static::deleting(function ($model)
		{
			// Only delete media if it's a force delete or model doesn't use soft deletes
			if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting())
			{
				return;
			}

			$model->media->each(fn($media) => $media->delete());
		});
	}
}
