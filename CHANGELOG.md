# Changelog

## 1.0.1 - 2026-10-09

- Upload-triggered optimization no longer converts formats. Converting at upload time deleted the original before an unsaved entry could be saved, leaving it pointing at a missing file. Conversion still runs from the Control Panel action, the CLI and the library button.
- Glide optimization is now off by default, since every generated variant costs one compression.
- Compress entire Asset library dispatches a single background job that queues the per-image jobs, so large libraries no longer risk request timeouts.
- PHP namespace renamed from `Tinify\Statamic` to `ThijsDeZoete\TinifyStatamic`, and the addon is listed as "Tinify for Statamic", an unofficial integration.

## 1.0.0 - 2026-10-09

First release.

- Automatic compression of JPEG, PNG, WebP, AVIF and SVG uploads via the TinyPNG API.
- Optional conversion to WebP, AVIF or the smallest format.
- Compression of Glide-generated images.
- Control Panel actions: Optimize with Tinify, Create thumbnail with Tinify.
- Compress entire asset library from the settings page, or per container with `php please tinify:optimize`.
- Utility dashboard with per-container counts, bytes saved and API credits.
