# Tinify for Statamic

Automatic image optimization for Statamic 6, powered by the Tinify/TinyPNG API. Supports JPEG, PNG, WebP and AVIF. GIF and SVG files are excluded.

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
| Optimize uploads automatically | On | Optimize new uploads and replaced files. |
| Preserve metadata | None | Optionally retain copyright, creation date and location metadata. |
| Convert format | Keep original | Opt into WebP, AVIF, or the smallest supported output format. |
| Optimize Glide images | Off | Compress generated variants after Glide writes them. |
| Asset containers | All | Limit original-asset optimization to the listed container handles. |

Prefer the environment variable for secrets. Statamic stores addon settings in its settings YAML; a key entered in the CP is not encrypted by this addon and must not be committed to source control.

### Conversion

Conversion is opt-in because it can change extensions and URLs. Statamic replaces the original asset and updates its managed content references. External or hard-coded URLs are not rewritten. Existing filenames are preserved by choosing a unique sibling name, such as `photo-1.webp`.

Requesting the asset's current format skips the paid conversion step. Without conversion, an equal-sized or larger result is discarded and the original is marked as checked.

### Glide

Glide compression is separately opt-in and applies to supported generated cache files. It does not convert or resize images again. Only a smaller result with the same media type replaces the cache file.

With an asynchronous queue, the first request serves the unoptimized variant. Each generated size costs a compression. Clearing and regenerating the cache bills again. Glide events do not identify the source container, so the container filter does not restrict this feature.

## Control Panel actions

Select images in the asset library:

- **Optimize with Tinify** queues optimization. Enable **Re-optimize already optimized images** to bypass the content-hash guard.
- **Create thumbnail with Tinify** creates a uniquely named sibling, leaving the source untouched. Choose dimensions and Smart crop (`thumb`), Cover or Fit. This explicit action is available independently of the container filter.

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
vendor/bin/phpunit
```

The offline suite uses real image files and Statamic assets with a fake client at the SDK boundary. It covers in-place metadata refresh, recursive event protection, conversion, destination collisions, error handling, upload triggers, actions, command selection, utility permissions and Glide cache rewriting. It does not call the live Tinify API.

To verify the live API, install the addon in a disposable host site, set a real `TINIFY_API_KEY`, and run `php please tinify:optimize assets --sync` against a large image. Confirm that the image still renders and that the asset size and `data.tinify.hash` metadata are updated. Also exercise a CP upload and, with Glide optimization enabled and an asynchronous queue, a generated image before and after running the worker.
