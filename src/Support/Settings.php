<?php

namespace Tinify\Statamic\Support;

use Statamic\Contracts\Assets\AssetContainer;
use Statamic\Facades\Addon;

class Settings
{
    public function apiKey(): ?string
    {
        $key = $this->get('api_key') ?: config('tinify.key');

        return $key ? (string) $key : null;
    }

    public function optimizeOnUpload(): bool
    {
        return (bool) $this->get('optimize_on_upload', true);
    }

    public function optimizeGlide(): bool
    {
        return (bool) $this->get('optimize_glide', false);
    }

    public function preserve(): array
    {
        return array_values(array_intersect(
            (array) $this->get('preserve', []),
            ['copyright', 'creation', 'location']
        ));
    }

    public function convertTypes(): ?array
    {
        return match ($this->get('convert', 'none')) {
            'smallest' => ['*/*'],
            'webp' => ['image/webp'],
            'avif' => ['image/avif'],
            default => null,
        };
    }

    public function appliesTo(AssetContainer $container): bool
    {
        $handles = (array) $this->get('containers', []);

        return $handles === [] || in_array($container->handle(), $handles, true);
    }

    private function get(string $key, mixed $default = null): mixed
    {
        return Addon::get('tinify/statamic')->settings()->get($key, $default);
    }
}
