<?php

namespace Tinify\Statamic\Actions;

use Statamic\Actions\Action;
use Statamic\Contracts\Assets\Asset;
use Tinify\Statamic\Jobs\OptimizeAsset;
use Tinify\Statamic\Support\Images;

class Optimize extends Action
{
    protected $icon = 'media-image-picture-gallery';

    public static function title()
    {
        return __('Optimize with Tinify');
    }

    public function visibleTo($item)
    {
        return $item instanceof Asset && $item->extensionIsOneOf(Images::EXTENSIONS);
    }

    public function authorize($user, $item)
    {
        return $user->can('edit', $item);
    }

    protected function fieldItems()
    {
        return [
            'force' => [
                'type' => 'toggle',
                'display' => __('Re-optimize already optimized images'),
                'default' => false,
            ],
        ];
    }

    public function run($items, $values)
    {
        foreach ($items as $asset) {
            OptimizeAsset::dispatch($asset->id(), force: (bool) ($values['force'] ?? false));
        }

        return trans_choice('Queued 1 image for optimization.|Queued :count images for optimization.', $items->count());
    }
}
