<?php

namespace Rdcstarr\LaravelMedia\Models;

use Exception;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Rdcstarr\LaravelMedia\Casts\MediaExtensionCast;
use Rdcstarr\LaravelMedia\Events\MediaCreated;
use Rdcstarr\LaravelMedia\Events\MediaDeleted;
use Rdcstarr\LaravelMedia\Events\MediaUpdated;

class Media extends Model
{
	public bool $afterCommit = true;

	protected $fillable = [
		'mediable_id',
		'mediable_type',
		'collection',
		'path',
		'extension',
		'disk',
		'size',
		'metadata',
	];

	protected $casts = [
		'size'      => 'integer',
		'metadata'  => 'array',
		'extension' => MediaExtensionCast::class,
	];

	protected $appends = [
		'url',
	];

	public function mediable(): MorphTo
	{
		return $this->morphTo();
	}

	public function url(): Attribute
	{
		return Attribute::get(fn() => Storage::disk($this->disk)->url($this->diskPath()));
	}

	protected static function booted(): void
	{
		static::created(function (Media $media)
		{
			event(new MediaCreated($media));
		});

		static::updated(function (Media $media)
		{
			if ($media->wasChanged('path') || $media->wasChanged('disk'))
			{
				$oldPath = $media->getOriginal('path');
				$oldDisk = $media->getOriginal('disk');

				if ($oldPath && $oldDisk)
				{
					try
					{
						$disk     = Storage::disk($oldDisk);
						$diskPath = ltrim($oldPath, '/');

						if ($disk->exists($diskPath))
						{
							$disk->delete($diskPath);
						}
					}
					catch (Exception $e)
					{
						logger()->error('Failed to delete old media file', [
							'old_path' => $oldPath,
							'old_disk' => $oldDisk,
							'media_id' => $media->id,
							'error'    => $e->getMessage(),
						]);
					}
				}
			}

			event(new MediaUpdated($media));
		});

		static::deleted(function (Media $media)
		{
			try
			{
				$disk = Storage::disk($media->disk);
				$path = $media->diskPath();

				if ($disk->exists($path))
				{
					$disk->delete($path);
				}
			}
			catch (Exception $e)
			{
				logger()->error('Failed to delete media file on deletion', [
					'path'     => $media->path,
					'disk'     => $media->disk,
					'media_id' => $media->id,
					'error'    => $e->getMessage(),
				]);
			}

			event(new MediaDeleted($media));
		});
	}

	protected function diskPath(?string $path = null): string
	{
		return ltrim($path ?? $this->path, '/');
	}
}
