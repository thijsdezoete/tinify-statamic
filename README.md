# Tinify for Statamic

Automatic image optimization for Statamic 6, powered by the Tinify/TinyPNG API. Supports JPEG, PNG, WebP, AVIF and SVG. GIF files are excluded.

## Install

Requires PHP 8.3 or later and Statamic 6 (Laravel 12 or 13).

For a local checkout beside your host site, run these commands from the host site's root:

```sh
composer config repositories.tinify path ../tinify-statamic
composer require tinify/statamic:@dev
```

Set the API key in your site's `.env`:

```dotenv
TINIFY_API_KEY=your-api-key
```

Uploads and file replacements are optimized automatically. Compression normally rewrites the original asset in place, preserving its URL and asset data. Statamic refreshes image metadata and invalidates its Glide cache. Keep backups of originals if you need to retain them: this addon does not create backup copies.

SVG files are compressed in place and keep their `.svg` filenames. Raster format conversion, resizing and metadata-preservation options do not apply to SVGs. Statamic's configured SVG sanitization remains active.

## Queues

The addon uses the site's default Laravel queue connection. With an asynchronous connection, run a worker:

```sh
php artisan queue:work
```

The original image remains available until its job runs. With `QUEUE_CONNECTION=sync`, optimization runs inside the upload or action request and adds API latency. Delayed retries require an asynchronous queue; the sync driver cannot provide them. Thumbnail creation is always synchronous.

Account/input errors are logged and are not retried. Connection/server errors release queued jobs with backoff; jobs allow three attempts. A missing key skips work and logs a notice at most once per hour. Inspect Laravel's logs and failed jobs for failures. Restart long-lived queue workers after changing the API key; the SDK key is initialized lazily once per client instance.

## Settings

Open **Tools → Addons → Tinify**.

| Setting | Default | Behavior |
| --- | --- | --- |
| API key | Environment variable | A saved key overrides `TINIFY_API_KEY`; blank uses the environment value. |
| Tinify API credits | Read-only | Remaining account-wide credits, including extra assigned credits, alongside monthly usage. Loads automatically and can be refreshed. |
| Optimize uploads automatically | On | Optimize new uploads and replaced files. |
| Preserve metadata | None | Optionally retain copyright, creation date and location metadata. |
| Convert format | Keep original | Opt into WebP, AVIF, or the smallest supported output format. |
| Optimize Glide images | On | Compress generated variants after Glide writes them. |
| Asset containers | All | Limit automatic upload optimization, **Optimize with Tinify**, the CLI and utility bulk operations. The explicit entire-library action covers all containers. |

Prefer the environment variable for secrets. Statamic stores addon settings in its settings YAML; a key entered in the CP is not encrypted by this addon and must not be committed to source control.

The credits display uses Tinify's reported remaining balance, not a calculation based on the free allowance. Like [Tinify's official WordPress plugin](https://github.com/tinify/wordpress-plugin/blob/master/src/class-tiny-compress-client.php), it requests `GET /keys/{key}` and reads the `Compression-Count-Remaining` and `Compression-Count` headers. It uses the saved API key, so save any key changes before clicking **Refresh**. Checking usage does not compress images; missing keys or unavailable readings are shown as unavailable, never as zero.

### Compress entire Asset library

The settings page includes a **Compress entire Asset library** button. It submits supported JPEG, PNG, WebP, AVIF and SVG images across every container, including containers excluded from automatic optimization. Only assets the current user can edit are included; access also requires permission to edit the Tinify addon settings.

Already checked images are skipped by default. Enable **Re-compress already checked images** to process them again, using additional API quota. A confirmation is required before submitting the library.

Save any settings changes before running this action: it uses the saved API key, conversion and metadata options, and the site's normal queue connection. Use an asynchronous queue and worker for large libraries. This action does not run automatically when settings are saved.

### Conversion

Conversion is opt-in for raster images because it can change extensions and URLs. Statamic replaces the original asset and updates its managed content references. External or hard-coded URLs are not rewritten. The basename is retained (`photo.png` becomes `photo.webp`); a suffix such as `photo-1.webp` is added only if the destination already exists, avoiding overwriting another asset.

Requesting the asset's current format skips the paid conversion step. Without conversion, an equal-sized or larger result is discarded and the original is marked as checked.

### Glide

Glide compression is enabled by default and applies to supported generated cache files. You can disable it in settings; an explicitly saved Off setting remains respected. It does not convert or resize images again. Only a smaller result with the same media type replaces the cache file.

With an asynchronous queue, the first request serves the unoptimized variant. Each generated size costs a compression. Clearing and regenerating the cache bills again. Glide events do not identify the source container, so the container filter does not restrict this feature.

## Control Panel actions

Select images in the asset library:

- **Optimize with Tinify** queues optimization. Enable **Re-optimize already optimized images** to bypass the content-hash guard.
- **Create thumbnail with Tinify** creates a uniquely named sibling from a raster image, leaving the source untouched. Choose dimensions and Smart crop (`thumb`), Cover or Fit. This explicit action is available independently of the container filter.

Both actions are also available in the individual asset editor's **⋯ menu**, after the built-in image controls. They require permission to edit the selected assets.

A content hash stored in the asset's `tinify` metadata prevents repeated API calls and the self-trigger loop caused by an in-place rewrite. Changing conversion settings does not automatically reprocess existing images; use the force option when desired.

## Bulk command

```sh
# Queue pending supported images across enabled containers.
php please tinify:optimize

# Limit to a container.
php please tinify:optimize assets

# Run immediately without a worker.
php please tinify:optimize assets --sync

# Reprocess marked images too (uses additional API quota).
php please tinify:optimize --force
```

The Artisan name is `statamic:tinify:optimize`. Bulk selection treats any image with a saved optimization hash as checked without rereading every file. Use `--force` after changes made outside Statamic. Excluded containers remain excluded even with `--force`.

## Utility dashboard

**Utilities → Tinify** shows API-key status, monthly compression count, checked/pending image counts, and byte reductions from the latest recorded operation on each asset. These are per-asset comparisons, not a historical disk-space audit; thumbnails retain their source files and forced runs replace previous statistics.

**Optimize all pending images** queues supported, unmarked images in enabled containers that the current user can edit. Access to the page requires the native **access tinify utility** permission.

Tinify counts basic compression as one operation; resizing and conversion each add another. Metadata preservation is free. The free tier includes 500 compressions per month; consult your Tinify account for your plan and usage.

## Development and verification

```sh
composer install
npm ci
npm run build
vendor/bin/phpunit
```

The settings action uses Statamic's native Vue UI components. After changing `resources/js`, run `npm run build` and include the generated `resources/dist` assets in the release. Consumer sites do not need Node or a frontend build. During development in a host site, publish the updated addon assets with `php artisan vendor:publish --provider="Tinify\Statamic\ServiceProvider" --force`.

The offline suite uses real image files and Statamic assets with a fake client at the SDK boundary. It covers in-place metadata refresh, recursive event protection, conversion, destination collisions, error handling, upload triggers, actions, command selection, utility permissions and Glide cache rewriting. It does not call the live Tinify API.

To verify the live API, install the addon in a disposable host site, set a real `TINIFY_API_KEY`, and run `php please tinify:optimize assets --sync` against a large image. Confirm that the image still renders and that the asset size and `data.tinify.hash` metadata are updated. Also exercise a CP upload and, with Glide optimization enabled and an asynchronous queue, a generated image before and after running the worker.
