<?php

namespace Tinify\Statamic\Listeners;

use Tinify\Statamic\Jobs\OptimizeAsset;
use Tinify\Statamic\Support\Images;
use Tinify\Statamic\Support\Settings;

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

        OptimizeAsset::dispatch($asset->id());
    }
}
