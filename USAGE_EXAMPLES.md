# Media Collection Events - Usage Examples

## Method 1: Dedicated Event Class

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Rdcstarr\Media\Traits\HasMedia;
use Rdcstarr\Media\Enums\MediaType;
use App\Events\Broadcasts\UserProfileImageUpdated;

class User extends Authenticatable
{
    use HasMedia;

    public function mediaCollection(): array
    {
        return [
            'profile_image_thumb'  => [
                'disk'       => 'public',
                'type'       => MediaType::Image,
                'width'      => 100,
                'height'     => 100,
                'fit'        => 'contain',
                'keep_original' => true, // Save original uploaded file
                'extensions' => [
                    'jpg'  => 85,
                    'webp' => 85,
                    'avif' => 100,
                ],
            ],
            'profile_image_medium' => [
                'disk'       => 'public',
                'type'       => MediaType::Image,
                'width'      => 400,
                'height'     => 400,
                'fit'        => 'contain',
                'extensions' => [
                    'jpg'  => 85,
                    'webp' => 85,
                    'avif' => 100,
                ],
            ],
            'profile_image_large'  => [
                'disk'       => 'public',
                'type'       => MediaType::Image,
                'width'      => 800,
                'height'     => 800,
                'fit'        => 'contain',
                'extensions' => [
                    'jpg'  => 85,
                    'webp' => 85,
                    'avif' => 100,
                ],
            ],
        ];
    }

    /**
     * Define custom events for media collections
     */
    public function mediaCollectionEvents(): array
    {
        return [
            // Option 1: Single event class for all actions
            'profile_image_thumb'  => UserProfileImageUpdated::class,
            'profile_image_medium' => UserProfileImageUpdated::class,
            'profile_image_large'  => UserProfileImageUpdated::class,

            // Option 2: Different events per action
            // 'profile_image_thumb' => [
            //     'created' => UserProfileImageCreated::class,
            //     'updated' => UserProfileImageUpdated::class,
            //     'deleted' => UserProfileImageDeleted::class,
            // ],

            // Option 3: Direct callback
            // 'profile_image_thumb' => function ($user, $media, $action) {
            //     UserEvent::dispatch($user, [
            //         'target' => 'user-profile-image',
            //         'action' => $action,
            //     ]);
            // },
        ];
    }
}
```

## Method 2: Event Class with Broadcasting Support

```php
<?php

namespace App\Events\Broadcasts;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Rdcstarr\Media\Models\Media;

class UserProfileImageUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
        public ?Media $media = null,
        public string $action = 'updated'
    ) {
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel|array
    {
        return new Channel('user.' . $this->user->id);
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'target' => 'user-profile-image',
            'action' => $this->action,
            'user_id' => $this->user->id,
            'media' => $this->media ? [
                'collection' => $this->media->collection,
                'extension' => $this->media->extension,
                'url' => $this->media->url,
            ] : null,
        ];
    }
}
```

## Method 3: Direct Callback in Model

```php
public function mediaCollectionEvents(): array
{
    return [
        'profile_image_thumb' => function ($user, $media, $action) {
            // You can do anything you want here
            UserEvent::dispatch($user, [
                'target' => 'user-profile-image',
                'action' => $action,
                'collection' => $media?->collection,
            ]);

            // Or you can have complex logic
            if ($action === 'created') {
                // Notify followers
                $user->followers->each(function ($follower) use ($user) {
                    $follower->notify(new UserProfileImageChanged($user));
                });
            }
        },
        'profile_image_medium' => fn($user, $media, $action) =>
            UserEvent::dispatch($user, ['target' => 'user-profile-image']),
        'profile_image_large' => fn($user, $media, $action) =>
            UserEvent::dispatch($user, ['target' => 'user-profile-image']),
    ];
}
```

## Method 4: Action-Specific Events

```php
public function mediaCollectionEvents(): array
{
    return [
        'profile_image_thumb' => [
            'created' => function ($user, $media, $action) {
                // Specific logic for creation
                event(new UserProfileImageCreated($user, $media));
            },
            'updated' => function ($user, $media, $action) {
                // Specific logic for update
                event(new UserProfileImageUpdated($user, $media));
            },
            'deleted' => function ($user, $media, $action) {
                // Specific logic for deletion
                event(new UserProfileImageDeleted($user, $media));
            },
        ],
    ];
}
```

## Method 5: Combined Events for All profile_image Collections

```php
public function mediaCollectionEvents(): array
{
    $profileImageEvent = function ($user, $media, $action) {
        // Same event for all 3 profile_image variants
        UserEvent::dispatch($user, [
            'target' => 'user-profile-image',
            'action' => $action,
            'collection' => $media?->collection,
        ]);
    };

    return [
        'profile_image_thumb'  => $profileImageEvent,
        'profile_image_medium' => $profileImageEvent,
        'profile_image_large'  => $profileImageEvent,
    ];
}
```

## Advantages Over Previous Method:

1. **Everything in the Model** - No need for a separate listener
2. **Type-safe** - You know exactly which collections have events
3. **Flexible** - You can use classes, callbacks, or combinations
4. **Clean** - Logic is where it should be (in the model, next to mediaCollection)
5. **Testable** - Easier to test than a global listener
6. **DRY** - You can reuse the same callback/class for multiple collections

## Important Note:

You can now delete the `ProfileImageUpdated` listener and implement the `mediaCollectionEvents()` method directly in the model.
Events will be automatically dispatched when media is created/updated/deleted.

## Saving the Original File (keep_original)

You can configure a collection to keep the original, unprocessed file alongside the processed versions:

```php
public function mediaCollection(): array
{
    return [
        'gallery' => [
            'disk'          => 'public',
            'type'          => MediaType::Image,
            'keep_original' => true, // Keep the original file
            'width'         => 1200,
            'height'        => 800,
            'fit'           => 'cover',
            'extensions'    => [
                'webp' => 90,
                'avif' => 85,
            ],
        ],
        'documents' => [
            'disk'          => 'private',
            'type'          => MediaType::File,
            'keep_original' => true, // Works for other file types too
            'extensions'    => ['pdf'],
        ],
    ];
}
```

### How It Works:

-   When `keep_original` is `true`, the original uploaded file will be saved with the extension marker `original` in the database
-   The actual file on disk will be saved as `{name}.original.{ext}` (e.g., `ulid.original.jpg`)
-   For images: in addition to the processed versions (webp, avif, etc.), you will also have the unmodified original
-   For other file types (video, audio, file): the original will be saved alongside the other defined extensions

### Accessing:

```php
// Access the original file
$originalUrl = $product->getMediaUrl('gallery', 'original');

// Or through the collection
$media = $product->getMediaCollection('gallery');
$original = $media->get('original');

// URL of the original
if ($original) {
    echo $original->url;
}
```

### Use Cases:

1. **High-Quality Images**: To allow downloading the original version without losses
2. **Future Editing**: To preserve the source material for future processing
3. **Archiving**: For backup and compliance
4. **Security**: To keep proof of the original in legal cases

### Note:

-   `keep_original` is `false` by default to save storage space
-   The original will be stored with extension marker `original` in the database
-   The file on disk will be saved as `{name}.original.{original_extension}`
-   Works for all media types: Image, Video, Audio, File
