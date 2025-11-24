# Laravel Media

[![Latest Version on Packagist](https://img.shields.io/packagist/v/rdcstarr/laravel-media.svg?style=flat-square)](https://packagist.org/packages/rdcstarr/laravel-media)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/rdcstarr/laravel-media/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/rdcstarr/laravel-media/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/rdcstarr/laravel-media/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/rdcstarr/laravel-media/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/rdcstarr/laravel-media.svg?style=flat-square)](https://packagist.org/packages/rdcstarr/laravel-media)

A flexible and powerful media management package for Laravel that handles images, videos, audio files, and documents with ease. Features automatic image optimization, multiple format support (WebP, AVIF), dynamic collections, and seamless Eloquent integration.

## Features

-   🖼️ **Image Processing**: Automatic resizing, cropping, and format conversion (WebP, AVIF, PNG, JPG)
-   📁 **Multiple File Types**: Support for images, videos, audio, and generic files
-   🔗 **Model-Centric Design**: Tightly integrated with Eloquent models
-   📦 **Collections**: Organize media files into named collections
-   🎯 **Flexible Configuration**: Per-collection settings for disk, path, dimensions, and more
-   🗑️ **Automatic Cleanup**: Files are deleted when models or media records are removed
-   🔄 **Soft Delete Support**: Respects soft deletes on parent models
-   📤 **URL Support**: Upload files directly from URLs
-   🏷️ **Metadata**: Store custom metadata with each media file
-   ⚡ **Events**: Listen to media created, updated, and deleted events

## Installation

You can install the package via composer:

```bash
composer require rdcstarr/laravel-media
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="laravel-media-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-media-config"
```

## Usage

### 1. Prepare Your Model

Add the `HasMedia` trait to your model and implement the `mediaCollection()` method:

```php
use Illuminate\Database\Eloquent\Model;
use Rdcstarr\LaravelMedia\Traits\HasMedia;

class Product extends Model
{
    use HasMedia;

    public function mediaCollection(): array
    {
        return [
            'thumbnail' => [
                'type' => 'image',
                'disk' => 'public',
                'path' => 'products/thumbnails',
                'width' => 300,
                'height' => 300,
                'fit' => 'contain',
                'extensions' => [
                    'webp' => 90,
                    'jpg' => 85,
                ],
            ],
            'gallery' => [
                'type' => 'image',
                'disk' => 'public',
                'path' => 'products/gallery',
                'width' => 1200,
                'extensions' => [
                    'webp' => 90,
                    'avif' => 80,
                ],
            ],
            'documents' => [
                'type' => 'file',
                'disk' => 'public',
                'path' => 'products/documents',
                'extensions' => ['pdf'],
            ],
        ];
    }
}
```

### 2. Upload Media Files

```php
use Illuminate\Http\UploadedFile;

$product = Product::find(1);

// Upload an image from request
$product->attachMedia()
    ->file($request->file('thumbnail'))
    ->addToCollection('thumbnail');

// Upload from URL
$product->attachMedia()
    ->file('https://example.com/image.jpg')
    ->addToCollection('gallery');

// Replace existing media in collection
$product->attachMedia()
    ->file($request->file('thumbnail'))
    ->replaceExisting()
    ->addToCollection('thumbnail');

// Keep original filename
$product->attachMedia()
    ->file($request->file('document'))
    ->keepOriginalFileName()
    ->addToCollection('documents');

// Add custom metadata
$product->attachMedia()
    ->file($request->file('image'))
    ->addToCollection('gallery', '', '', [
        'alt_text' => 'Product showcase',
        'caption' => 'Summer collection 2024',
    ]);
```

### 3. Retrieve Media

```php
// Get media collection
$thumbnails = $product->getMediaCollection('thumbnail');

// Get specific media URL
$url = $product->getMediaUrl('thumbnail', 'webp');

// Get media size
$size = $product->getMediaSize('thumbnail', 'webp');

// Get metadata
$metadata = $product->getMediaMetaData('gallery', 'webp');

// Check if media exists
if ($product->hasMedia('thumbnail')) {
    // ...
}

// Using magic methods (CamelCase collection names)
$galleryCollection = $product->getMediaGalleryCollection();
$thumbnailUrl = $product->getMediaThumbnailUrl('webp');
$documentSize = $product->getMediaDocumentsSize('pdf');
```

### 4. Delete Media

```php
// Clear entire collection
$product->clearMediaCollection('gallery');

// Clear specific extensions from collection
$product->clearMediaCollection('gallery', ['jpg', 'png']);

// Media files are automatically deleted when:
// - The media record is deleted
// - The parent model is deleted (respects soft deletes)
// - A media file is replaced in a collection
```

## Configuration Options

### Collection Configuration

Each collection in `mediaCollection()` supports these options:

```php
[
    'type' => 'image|video|audio|file',  // Media type (default: auto-detected)
    'disk' => 'public',                   // Storage disk (default: 'public')
    'path' => '{model}/{collection}',     // Storage path (supports placeholders)
    'name' => '{ulid}',                   // Filename pattern (supports placeholders)
    'visibility' => 'public|private',     // File visibility (optional)

    // Image-specific options
    'width' => 1200,                      // Target width in pixels
    'height' => 800,                      // Target height in pixels
    'fit' => 'contain|cover|fill|...',    // Spatie\Image\Enums\Fit value
    'extensions' => [                     // Output formats with quality
        'webp' => 90,
        'avif' => 80,
        'jpg' => 85,
    ],

    // Non-image file options
    'extension' => 'pdf',                 // Single extension for non-images
    'extensions' => ['pdf', 'doc'],       // Multiple extensions for non-images
]
```

### Path and Name Placeholders

Available placeholders for `path` and `name`:

-   `{ulid}` - Generates a ULID (default for name)
-   `{uuid}` - Generates a UUID
-   `{model}` - Lowercase model class name
-   `{collection}` - Collection name

Example:

```php
'path' => '{model}/{collection}/{ulid}',  // products/gallery/01HQXYZ...
'name' => 'img-{uuid}',                   // img-123e4567-e89b...
```

## Events

The package dispatches the following events:

-   `Rdcstarr\LaravelMedia\Events\MediaCreated`
-   `Rdcstarr\LaravelMedia\Events\MediaUpdated`
-   `Rdcstarr\LaravelMedia\Events\MediaDeleted`

Listen to these events in your `EventServiceProvider`:

```php
protected $listen = [
    \Rdcstarr\LaravelMedia\Events\MediaCreated::class => [
        \App\Listeners\ProcessMediaCreated::class,
    ],
];
```

## Architecture

The package is built with a **model-centric architecture**:

1. **LaravelMedia** class requires a model instance in the constructor
2. Models using **HasMedia** trait get an `attachMedia()` helper method
3. All media operations are tied to the parent model
4. Files are automatically cleaned up when models or media are deleted

This design ensures:

-   Type safety and validation at instantiation
-   Clear ownership of media files
-   Automatic lifecycle management
-   No orphaned files in storage

## 🧪 Testing

```bash
composer test
```

## 📖 Resources

-   [Changelog](CHANGELOG.md) for more information on what has changed recently. ✍️

## 👥 Credits

-   [Rdcstarr](https://github.com/rdcstarr) 🙌

## 📜 License

-   [License](LICENSE.md) for more information. ⚖️
