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
            // Opțiune 1: Un singur event class pentru toate acțiunile
            'profile_image_thumb'  => UserProfileImageUpdated::class,
            'profile_image_medium' => UserProfileImageUpdated::class,
            'profile_image_large'  => UserProfileImageUpdated::class,

            // Opțiune 2: Evenimente diferite per acțiune
            // 'profile_image_thumb' => [
            //     'created' => UserProfileImageCreated::class,
            //     'updated' => UserProfileImageUpdated::class,
            //     'deleted' => UserProfileImageDeleted::class,
            // ],

            // Opțiune 3: Callback direct
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

## Metoda 2: Event Class cu suport pentru Broadcasting

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

## Metoda 3: Callback direct în model

```php
public function mediaCollectionEvents(): array
{
    return [
        'profile_image_thumb' => function ($user, $media, $action) {
            // Poți face orice vrei aici
            UserEvent::dispatch($user, [
                'target' => 'user-profile-image',
                'action' => $action,
                'collection' => $media?->collection,
            ]);

            // Sau poți avea logică complexă
            if ($action === 'created') {
                // Notifică followers
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

## Metoda 4: Evenimente specifice per acțiune

```php
public function mediaCollectionEvents(): array
{
    return [
        'profile_image_thumb' => [
            'created' => function ($user, $media, $action) {
                // Logică specifică pentru creare
                event(new UserProfileImageCreated($user, $media));
            },
            'updated' => function ($user, $media, $action) {
                // Logică specifică pentru actualizare
                event(new UserProfileImageUpdated($user, $media));
            },
            'deleted' => function ($user, $media, $action) {
                // Logică specifică pentru ștergere
                event(new UserProfileImageDeleted($user, $media));
            },
        ],
    ];
}
```

## Metoda 5: Combinație de toate colecțiile profile_image

```php
public function mediaCollectionEvents(): array
{
    $profileImageEvent = function ($user, $media, $action) {
        // Același event pentru toate cele 3 variante de profile_image
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

## Avantaje față de metoda anterioară:

1. **Totul în model** - Nu mai ai nevoie de listener separat
2. **Type-safe** - Știi exact ce colecții au evenimente
3. **Flexibil** - Poți folosi clase, callbacks sau combinații
4. **Clean** - Logica este acolo unde trebuie (în model, lângă mediaCollection)
5. **Testabil** - Mai ușor de testat decât un listener global
6. **DRY** - Poți refolosi același callback/class pentru multiple colecții

## Notă importantă:

Acum poți șterge listener-ul `ProfileImageUpdated` și să implementezi direct în model metoda `mediaCollectionEvents()`.
Evenimentele se vor declanșa automat când media este created/updated/deleted.

## Salvarea fișierului original (keep_original)

Poți configura o colecție să păstreze și fișierul original, neprocesat, alături de versiunile procesate:

```php
public function mediaCollection(): array
{
    return [
        'gallery' => [
            'disk'          => 'public',
            'type'          => MediaType::Image,
            'keep_original' => true, // Păstrează fișierul original
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
            'keep_original' => true, // Funcționează și pentru alte tipuri de fișiere
            'extensions'    => ['pdf'],
        ],
    ];
}
```

### Cum funcționează:

-   Când `keep_original` este `true`, fișierul original uploadat va fi salvat cu extensia `original.{ext}`
-   Pentru imagini: pe lângă versiunile procesate (webp, avif, etc.), vei avea și originalul nemodificat
-   Pentru alte tipuri de fișiere (video, audio, file): originalul va fi salvat alături de celelalte extensii definite

### Accesare:

```php
// Accesează fișierul original
$originalUrl = $product->getMediaUrl('gallery', 'original.jpg');

// Sau prin colecție
$media = $product->getMediaCollection('gallery');
$original = $media->get('original.jpg');

// URL-ul originalului
if ($original) {
    echo $original->url;
}
```

### Cazuri de utilizare:

1. **Imagini de înaltă calitate**: Pentru a permite descărcarea versiunii originale fără pierderi
2. **Editare ulterioară**: Pentru a păstra materialul sursă pentru procesări viitoare
3. **Arhivare**: Pentru backup și conformitate
4. **Securitate**: Pentru a păstra dovada originalului în cazuri legale

### Notă:

-   `keep_original` este `false` by default pentru a economisi spațiu de stocare
-   Originalul va fi salvat întotdeauna cu numele pattern: `original.{extension_originala}`
-   Funcționează pentru toate tipurile de media: Image, Video, Audio, File
