# Release procedure

The release path is **GitHub → Packagist → Statamic Marketplace**. Start with a small beta, then tag `1.0.0` after real-API testing and a clean installation.

## 1. Confirm ownership, name, and license

**Check the Composer vendor name first.** The package currently uses `tinify/statamic`. Packagist protects existing vendor namespaces: publishing under `tinify/` requires your account to maintain a package in that namespace. If this is an official Tinify release, arrange that access; otherwise use a vendor namespace you control.

See [Packagist's naming rules](https://packagist.org/about#naming-your-package).

Also decide:

- **Free or paid addon.**
- **License:** MIT is a straightforward choice for a free, open-source addon; paid distribution needs an appropriate license.
- **Support channel:** GitHub Issues, an email address, or your existing support system.

Do this before publishing—the package name becomes part of users' installations.

## 2. Finish release verification

The offline tests and CP checks passed. **Live Tinify behavior is the main remaining verification gap.**

Use a clean host with a real API key and **without the preview host's fake-client provider**. Check:

- Upload and replacement optimization.
- Thumbnail creation.
- WebP/AVIF conversion, including existing content references.
- SVG compression, preserving the `.svg` filename and avoiding repeat requests after sanitization.
- Bulk CLI and queued Glide compression.
- Invalid-key/quota failures without damage to the original asset.

Because the addon rewrites files, also perform a focused review of concurrent jobs, storage failures, and conversion safety before customer use.

Add CI for the supported PHP/Laravel combinations and minimum/latest supported Statamic versions. At the time this procedure was prepared, the suite had run on **PHP 8.4.26, Laravel 13, Statamic 6.35**—not the whole advertised compatibility range.

## 3. Prepare the repository for users

The package still needs:

- `LICENSE`.
- `CHANGELOG.md`, starting with the first release.
- Composer metadata: license, author/organization, homepage and support links.
- A GitHub Actions test workflow.
- Published-package installation instructions in `README.md`; it currently documents a local path-repository installation.

Make the external-service requirements prominent: images are sent to Tinify, an API key is required, quota charges apply, originals are rewritten, and conversion can change URLs.

Publish **only the addon repository**, not the preview host, API keys, or uploaded screenshots.

## 4. Publish a beta through Packagist

1. Create the public GitHub repository and push the release-ready code.
2. Run Composer validation and the tests.
3. Tag a prerelease, for example **`v0.1.0-beta.1`**.
4. [Submit the repository to Packagist](https://packagist.org/packages/submit).
5. Enable GitHub/Packagist automatic updates.

Composer derives versions from Git tags; do not add a hard-coded `version` field to `composer.json`.

Then install that **exact published beta into a fresh Statamic site**, without a local path repository. That catches packaging problems the development checkout cannot expose. Have a few users try it on staging sites with backed-up assets.

## 5. Prepare the Statamic Marketplace listing

Create a [Statamic creator account](https://statamic.com/creator/begin), link the Packagist package, and prepare:

- A concise description and feature list.
- Actual CP screenshots: settings, asset actions, utility dashboard.
- Compatibility requirements and installation instructions.
- Clear Tinify account/quota disclosures.
- Documentation, changelog and support links.

For a paid addon, complete the commercial licensing and payment setup too.

**Marketplace publication now includes review**, for both free and paid products. Work through the current [submission guidelines](https://statamic.com/marketplace/submission-guidelines) before submitting—especially clean installation, data safety, documentation and accurate screenshots.

## 6. Release `1.0.0`

After beta feedback is resolved:

- Tag `v1.0.0` and publish release notes.
- Confirm Packagist picked it up.
- Verify a normal Composer installation once more.
- Submit/finalize the Marketplace listing.

## Immediate next steps

Decide the publishing account/package name and license. The next technical step is real-API testing—not more features.
