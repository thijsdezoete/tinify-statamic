<?php

namespace Tinify\Statamic\Support;

use Statamic\Contracts\Assets\Asset;

class Images
{
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'avif'];

    public static function uniqueSiblingPath(Asset $asset, string $filename, string $extension): string
    {
        $folder = trim($asset->folder(), '/');
        $stem = ($folder !== '' ? $folder.'/' : '').$filename;
        $path = $stem.'.'.$extension;

        for ($suffix = 1; $asset->container()->asset($path); $suffix++) {
            $path = $stem.'-'.$suffix.'.'.$extension;
        }

        return $path;
    }
}
