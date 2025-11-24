# Changelog

All notable changes to `laravel-media` will be documented in this file.

## [Unreleased]

### Added

-   Enum support for better type safety and IDE autocomplete
    -   `MediaType` enum for media types (Image, Video, Audio, File)
    -   `ImageExtension` enum for common image formats (JPEG, PNG, WEBP, AVIF, etc.)
    -   `VideoExtension` enum for video formats (MP4, WEBM, OGG, AVI, MOV, etc.)
    -   `AudioExtension` enum for audio formats (MP3, WAV, OGG, M4A, AAC, etc.)
    -   `MediaExtensionCast` for automatic enum/string conversion in Media model
-   Helper methods on enums: `values()`, `fromString()`, `isValid()`
-   Full backward compatibility with string values in configuration

### Changed

-   `detectType()` method now returns `MediaType` enum instead of string
-   Added `resolveMediaType()` method for flexible type handling (accepts both string and enum)
-   Updated Media model to use `MediaExtensionCast` for extension field
