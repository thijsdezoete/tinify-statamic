<?php

namespace ThijsDeZoete\TinifyStatamic\Listeners;

use ThijsDeZoete\TinifyStatamic\Jobs\OptimizeAsset;
use ThijsDeZoete\TinifyStatamic\Support\Images;
use ThijsDeZoete\TinifyStatamic\Support\Settings;

class OptimizeUploadedAsset
{
    public function __construct(private Settings $settings) {}

    public function handle($event): void
    {
        $asset = $event->asset;

        if (! $this->settings->optimizeOnUpload()
            || ! $asset->extensionIsOneOf(Images::EXTENSIONS)
            || ! $this->settings->appliesTo($asset->container())) {
            return;
        }

        // Never convert here: the entry editor still holds the uploaded path and has not saved it yet.
        OptimizeAsset::dispatch($asset->id(), convert: false);
    }
}
