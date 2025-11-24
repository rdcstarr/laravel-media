<?php

namespace Rdcstarr\Media\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Rdcstarr\Media\Models\Media;

class MediaUpdated
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
