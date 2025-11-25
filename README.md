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

### Automatic Installation (Recommended)

Run the install command to publish and run the migrations:

```bash
php artisan media:install
```

### Manual Installation

Alternatively, you can install manually:

1. Publish the migrations:

```bash
php artisan vendor:publish --provider="Rdcstarr\Media\MediaServiceProvider" --tag="laravel-media-migrations"
```

2. Run the migrations:

```bash
php artisan migrate
```

## Usage

### Setup Model

```php
use Rdcstarr\Media\Traits\HasMedia;
use Rdcstarr\Media\Enums\{MediaType, ImageExtension};

class Product extends Model
{
    use HasMedia;

    public function mediaCollection(): array
    {
        return [
            'thumbnail' => [
                'type' => MediaType::Image,
                'width' => 300,
                'height' => 300,
                'extensions' => [ImageExtension::WEBP => 90, ImageExtension::JPG => 85],
            ],
            'gallery' => [
                'type' => MediaType::Image,
                'width' => 1200,
                'extensions' => [ImageExtension::WEBP => 90, ImageExtension::AVIF => 80],
            ],
        ];
    }
}
```

### Upload & Retrieve

```php
// Upload
$product->attachMedia()->file($request->file('image'))->addToCollection('gallery');
$product->attachMedia()->file('https://example.com/image.jpg')->replaceExisting()->addToCollection('thumbnail');

// Retrieve
$url = $product->getMediaUrl('thumbnail', 'webp');
$product->getMediaThumbnailUrl('webp');  // Magic method
$product->clearMediaCollection('gallery', ['jpg']);  // Delete
```

## Configuration

### Collection Options

```php
[
    'type' => MediaType::Image,           // Media type (Image|Video|Audio|File)
    'disk' => 'public',                   // Storage disk
    'path' => '{model}/{collection}',     // Path with placeholders: {ulid}, {uuid}, {model}, {collection}
    'name' => '{ulid}',                   // Filename pattern
    'visibility' => 'public',             // File visibility
    'width' => 1200,                      // Image width (px)
    'height' => 800,                      // Image height (px)
    'fit' => 'contain',                   // Fit mode (contain|cover|fill)
    'extensions' => [                     // Formats with quality
        ImageExtension::WEBP => 90,
        ImageExtension::AVIF => 80,
    ],
]
```

### Enums (Type-Safe)

-   **MediaType**: `Image`, `Video`, `Audio`, `File`
-   **ImageExtension**: `JPEG`, `JPG`, `PNG`, `WEBP`, `AVIF`, `GIF`, `SVG`, `BMP`, `TIFF`
-   **VideoExtension**: `MP4`, `WEBM`, `OGG`, `AVI`, `MOV`, `WMV`, `FLV`, `MKV`, `M4V`
-   **AudioExtension**: `MP3`, `WAV`, `OGG`, `M4A`, `AAC`, `FLAC`, `WMA`, `OPUS`

## Events & Architecture

**Events:** `MediaCreated`, `MediaUpdated`, `MediaDeleted`

**Model-centric design:** All operations require a model instance, ensuring type safety, clear ownership, and automatic cleanup of files when models or media are deleted.

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
