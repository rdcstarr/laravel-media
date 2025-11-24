<?php

namespace Rdcstarr\LaravelMedia\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Rdcstarr\LaravelMedia\Models\Media;

class MediaDeleted
{
	use Dispatchable, InteractsWithSockets, SerializesModels;

	/**
	 * Create a new event instance.
	 */
	public function __construct(
		public Media $media,
	) {
		//
	}
}
