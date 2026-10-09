<?php

namespace ThijsDeZoete\TinifyStatamic\Listeners;

use ThijsDeZoete\TinifyStatamic\Jobs\OptimizeGlideImage;
use ThijsDeZoete\TinifyStatamic\Support\Images;
use ThijsDeZoete\TinifyStatamic\Support\Settings;

class OptimizeGeneratedGlideImage
{
    public function __construct(private Settings $settings) {}

    public function handle($event): void
    {
        if (! $this->settings->optimizeGlide()
            || ! in_array(strtolower(pathinfo($event->path, PATHINFO_EXTENSION)), Images::EXTENSIONS, true)) {
            return;
        }

        OptimizeGlideImage::dispatch($event->path);
    }
}
