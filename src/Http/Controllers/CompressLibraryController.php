<?php

namespace ThijsDeZoete\TinifyStatamic\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Statamic\Facades\Addon;
use Statamic\Http\Controllers\CP\CpController;
use ThijsDeZoete\TinifyStatamic\Jobs\CompressLibrary;
use ThijsDeZoete\TinifyStatamic\Support\Settings;

class CompressLibraryController extends CpController
{
    public function __invoke(Request $request, Settings $settings): array
    {
        $this->authorize('editSettings', Addon::get('thijsdezoete/tinify-statamic'));

        $request->validate(['force' => 'sometimes|boolean']);

        if (! $settings->apiKey()) {
            throw ValidationException::withMessages([
                'api_key' => __('Save a Tinify API key in the addon settings or configure TINIFY_API_KEY before compressing the library.'),
            ]);
        }

        CompressLibrary::dispatch($request->user()->id(), $request->boolean('force'));

        return [
            'message' => __('Library compression started. Images are queued in the background; progress is shown in Utilities → Tinify.'),
        ];
    }
}
