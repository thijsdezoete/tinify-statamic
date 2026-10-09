# Tinify for Statamic

Automatic image optimization for Statamic 6, powered by the [TinyPNG](https://tinypng.com) API.

Upload an image, and it is compressed in place before anyone sees it. Optionally convert to WebP or AVIF, compress Glide-generated variants, create smart-cropped thumbnails, and bulk-optimize an existing library from the Control Panel or the command line.

Supported formats: JPEG, PNG, WebP, AVIF and SVG. GIF files are skipped.

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [How it works](#how-it-works)
- [Settings](#settings)
- [Control Panel actions](#control-panel-actions)
- [Bulk optimization](#bulk-optimization)
- [Utility dashboard](#utility-dashboard)
- [Format conversion](#format-conversion)
- [Glide images](#glide-images)
- [Queues and error handling](#queues-and-error-handling)
- [Costs and credits](#costs-and-credits)
- [Troubleshooting](#troubleshooting)
- [Development](#development)

## Requirements

- PHP 8.3 or later
- Statamic 6 (Laravel 12 or 13)
- A Tinify API key, available free at [tinypng.com/developers](https://tinypng.com/developers)

## Installation

```sh
composer require thijsdezoete/tinify-statamic
```

Add your API key to the site's `.env`:

```dotenv
TINIFY_API_KEY=your-api-key
```

That's it. New uploads are optimized automatically from this point on.

### Installing from a local checkout

To develop against the addon from a sibling directory, run this from the host site's root:

```sh
composer config repositories.tinify path ../tinify-statamic
composer require thijsdezoete/tinify-statamic:@dev
```

## Quick start

1. Install and set `TINIFY_API_KEY` as above.
2. Open **Tools → Addons → Tinify** in the Control Panel. The credits widget confirms the key is valid and shows your remaining balance.
3. Upload an image to any asset container. It is compressed and replaced in place.
4. To optimize an existing library, click **Compress entire Asset library** on the settings page, or run:

   ```sh
   php please tinify:optimize
   ```

## How it works

The addon listens for Statamic's asset upload and re-upload events and sends the file to Tinify. The compressed result overwrites the original asset. The asset's URL, path and field data are preserved. Statamic then refreshes the image metadata and invalidates its Glide cache for that asset.

Each optimized asset stores a content hash in its `tinify` metadata. This prevents repeat API calls for an image that was already processed, and stops the in-place rewrite from triggering itself again. Changing conversion or metadata settings does not automatically reprocess existing images. Use the force options described below when you want that.

SVG files are compressed in place and keep their `.svg` filename. Format conversion, resizing and metadata preservation do not apply to SVGs. Statamic's SVG sanitization remains active.

> **No backups are made.** Compression rewrites the original file. Keep your own copies if you need the unoptimized originals.

## Settings

Open **Tools → Addons → Tinify**.

| Setting | Default | Behavior |
| --- | --- | --- |
| API key | Environment variable | A key saved here overrides `TINIFY_API_KEY`. Leave blank to use the environment value. |
| Tinify API credits | Read-only | Remaining credits and this month's compression count, read live from your Tinify account. |
| Optimize uploads automatically | On | Optimize new uploads and replaced files. |
| Preserve metadata | None | Keep copyright, creation date and/or location metadata. Free, raster images only. |
| Convert format | Keep original | Convert raster images to WebP, AVIF, or whichever supported format is smallest. See [Format conversion](#format-conversion). |
| Optimize Glide images | On | Compress Glide-generated variants after they are written. See [Glide images](#glide-images). |
| Asset containers | All | Limit automatic optimization, the **Optimize with Tinify** action, the CLI and the utility to these container handles. The **Compress entire Asset library** button ignores this filter. |

Prefer the environment variable for the API key. Statamic stores addon settings as YAML in your repository, and a key entered in the Control Panel is not encrypted. Do not commit it.

The credits widget reads your real remaining balance from Tinify, the same way the official WordPress plugin does. It uses the saved key, so save the form before clicking **Refresh**. Checking the balance does not consume a compression. An unavailable reading is shown as unavailable, never as zero.

### Compress entire Asset library

This button on the settings page queues every supported image across all containers, including containers excluded from automatic optimization. Only assets the current user can edit are included, and the user needs permission to edit Tinify settings.

Already optimized images are skipped unless you enable **Re-compress already checked images**, which uses additional credits. You will be asked to confirm before anything is queued.

Save your settings first. The action uses the saved API key, conversion and metadata options. For large libraries, use an asynchronous queue with a running worker.

## Control Panel actions

Select one or more images in the asset browser, or open the **⋯** menu in an asset's editor.

**Optimize with Tinify** queues optimization for the selected images. Enable **Re-optimize already optimized images** to bypass the content-hash check.

**Create thumbnail with Tinify** generates a resized copy next to the source image, leaving the source untouched. Choose a width and height and a resize method:

| Method | Behavior |
| --- | --- |
| Smart crop | Tinify detects the subject and crops around it. |
| Cover | Scales to cover the dimensions, cropping the overflow from the center. |
| Fit | Scales to fit inside the dimensions without cropping. |

Thumbnails are created synchronously and get a unique filename if the target already exists. This action works regardless of the container filter.

Both actions require permission to edit the selected assets.

## Bulk optimization

```sh
# Queue every pending supported image in enabled containers.
php please tinify:optimize

# Limit to one container.
php please tinify:optimize assets

# Run jobs immediately instead of queueing.
php please tinify:optimize assets --sync

# Reprocess already optimized images too. Uses additional credits.
php please tinify:optimize --force
```

The Artisan equivalent is `php artisan statamic:tinify:optimize`.

Images with a stored optimization hash are treated as done without re-reading the file. Use `--force` after images were changed outside Statamic. Containers excluded in settings stay excluded, even with `--force`.

## Utility dashboard

**Utilities → Tinify** shows the API key status, this month's compression count, how many images are optimized or pending, and the byte reduction from the most recent operation on each asset. These are per-asset figures from the last run, not a historical audit.

**Optimize all pending images** queues unoptimized images in enabled containers that the current user can edit. Viewing the page requires the **access tinify utility** permission.

## Format conversion

Conversion is opt-in because it changes file extensions and therefore URLs. When enabled, Statamic replaces the asset and updates references in managed content. Hard-coded or external URLs are not rewritten.

The basename is kept: `photo.png` becomes `photo.webp`. If that path already exists, a suffix such as `photo-1.webp` is used so no other asset is overwritten.

If the image is already in the requested format, the paid conversion step is skipped. Without conversion, a result that is not smaller than the original is discarded and the original is marked as checked.

## Glide images

When enabled, each Glide-generated variant is compressed after Glide writes it to the cache. The variant is not resized or converted again. Only a smaller result of the same media type replaces the cached file.

With an asynchronous queue, the first request serves the unoptimized variant and the compressed version lands once the job runs.

Every generated size costs one compression, and clearing the Glide cache bills again when variants are regenerated. Glide events do not identify the source container, so the container filter does not apply here. Turn this setting off if your site generates many sizes and you want to conserve credits.

## Queues and error handling

The addon dispatches jobs on your default Laravel queue connection.

- With an asynchronous connection, run `php artisan queue:work`. The original image stays available until its job runs.
- With `QUEUE_CONNECTION=sync`, optimization runs inside the upload request and adds Tinify's round-trip latency to it. Delayed retries are not possible on the sync driver.
- Thumbnail creation is always synchronous.

Account and input errors, such as an invalid key or exhausted quota, are logged and not retried. The original asset is left untouched. Connection and server errors release the job with backoff, for up to three attempts. A missing API key skips all work and logs a notice at most once per hour.

The API key is read once per client instance. Restart long-running queue workers after changing it.

## Costs and credits

Tinify counts a basic compression as one operation. Resizing and format conversion each add one more. Metadata preservation is free. The free tier includes 500 compressions per month. Check your Tinify account for your plan and current usage, or use the credits widget on the settings page.

## Troubleshooting

**Uploads are not being optimized.**
Check that **Optimize uploads automatically** is on, the container is not excluded, the file is a supported format and not a GIF, and a queue worker is running if you use an asynchronous queue. Then check `storage/logs` for Tinify notices.

**The credits widget says unavailable.**
The saved key is missing or invalid. Save the settings form and click **Refresh**, or set `TINIFY_API_KEY` and leave the form field blank.

**An image was optimized but conversion did not happen.**
Existing images are not reprocessed when you change settings. Run `php please tinify:optimize --force` or use the force option on the CP action.

**Glide variants look the same size.**
The first request serves the unoptimized variant when using an asynchronous queue. Run the worker and reload.

## Development

```sh
composer install
npm ci
npm run build
vendor/bin/phpunit
```

The Control Panel components in `resources/js` use Statamic's native Vue UI library. After changing them, run `npm run build` and commit the generated `resources/dist` files. Sites installing the addon do not need Node.

When developing inside a host site, republish the built assets with:

```sh
php artisan vendor:publish --provider="Tinify\Statamic\ServiceProvider" --force
```

The test suite runs offline against real image files and Statamic assets, with a fake client at the SDK boundary. It covers in-place rewriting and metadata refresh, event loop protection, conversion and destination collisions, error handling, upload triggers, CP actions, the CLI, utility permissions and Glide cache rewriting. No test calls the live API.

To verify against the live API, install the addon in a disposable site with a real key and run `php please tinify:optimize assets --sync` on a large image. Confirm the image still renders and that its size and `data.tinify.hash` metadata changed. Then exercise a CP upload and, with an asynchronous queue, a Glide variant before and after the worker runs.

## Support

Report bugs and request features at [github.com/thijsdezoete/tinify-statamic/issues](https://github.com/thijsdezoete/tinify-statamic/issues).
