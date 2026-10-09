<?php

namespace ThijsDeZoete\TinifyStatamic\Actions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Statamic\Actions\Action;
use Statamic\Contracts\Assets\Asset;
use Tinify\Exception;
use ThijsDeZoete\TinifyStatamic\Api\Client;
use ThijsDeZoete\TinifyStatamic\Support\Images;

class CreateThumbnail extends Action
{
    protected $icon = 'crop';

    public static function title()
    {
        return __('Create thumbnail with Tinify');
    }

    public function visibleTo($item)
    {
        return $item instanceof Asset && $item->extensionIsOneOf(Images::RASTER_EXTENSIONS);
    }

    public function authorize($user, $item)
    {
        return $user->can('edit', $item);
    }

    protected function fieldItems()
    {
        return [
            'width' => [
                'type' => 'integer',
                'display' => __('Width'),
                'validate' => 'required|integer|min:1',
            ],
            'height' => [
                'type' => 'integer',
                'display' => __('Height'),
                'validate' => 'required|integer|min:1',
            ],
            'method' => [
                'type' => 'select',
                'display' => __('Resize method'),
                'options' => [
                    'thumb' => __('Smart crop'),
                    'cover' => __('Cover'),
                    'fit' => __('Fit'),
                ],
                'default' => 'thumb',
                'clearable' => false,
                'validate' => 'required|in:thumb,cover,fit',
            ],
        ];
    }

    public function run($items, $values)
    {
        Validator::make($values, [
            'width' => 'required|integer|min:1',
            'height' => 'required|integer|min:1',
            'method' => 'required|in:thumb,cover,fit',
        ])->validate();

        $width = (int) $values['width'];
        $height = (int) $values['height'];
        $client = app(Client::class);

        foreach ($items as $asset) {
            $bytes = $asset->contents();

            try {
                $result = $client->optimize($bytes, resize: [
                    'method' => $values['method'],
                    'width' => $width,
                    'height' => $height,
                ]);
            } catch (Exception $exception) {
                throw ValidationException::withMessages(['tinify' => $exception->getMessage()]);
            }

            $path = Images::uniqueSiblingPath($asset, $asset->filename()."-{$width}x{$height}", $result->extension());
            $thumbnail = $asset->container()->makeAsset($path);
            if (! $thumbnail->disk()->put($path, $result->bytes)) {
                throw ValidationException::withMessages(['tinify' => __('Could not write the thumbnail.')]);
            }
            $thumbnail->set('tinify', [
                'hash' => sha1($result->bytes),
                'optimized_at' => now()->timestamp,
                'original_size' => strlen($bytes),
                'size' => strlen($result->bytes),
            ])->save();
        }

        return trans_choice('Created 1 thumbnail.|Created :count thumbnails.', $items->count());
    }
}
