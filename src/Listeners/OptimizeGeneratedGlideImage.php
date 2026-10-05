<?php

namespace Tinify\Statamic\Listeners;

use Tinify\Statamic\Jobs\OptimizeGlideImage;
use Tinify\Statamic\Support\Images;
use Tinify\Statamic\Support\Settings;

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
