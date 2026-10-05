<div>
    <ui-header title="Tinify"></ui-header>

    <ui-panel heading="{{ __('Tinify account') }}" class="mb-6">
        <ui-card>
            <dl class="grid gap-4 sm:grid-cols-3">
                <div>
                    <dt class="font-medium">{{ __('API key') }}</dt>
                    <dd>{{ $keyValid ? __('Valid') : __('Missing, invalid, or unavailable') }}</dd>
                </div>
                <div>
                    <dt class="font-medium">{{ __('Compressions this month') }}</dt>
                    <dd>{{ $compressionCount === null ? __('Unavailable') : number_format($compressionCount) }}</dd>
                </div>
                <div>
                    <dt class="font-medium">{{ __('Net bytes saved') }}</dt>
                    <dd>{{ number_format($bytesSaved) }} {{ __('bytes') }}</dd>
                </div>
            </dl>
            <p class="mt-4"><a href="{{ $settingsUrl }}">{{ __('Manage Tinify settings') }}</a></p>
        </ui-card>
    </ui-panel>

    <ui-panel heading="{{ __('Asset optimization') }}" subheading="{{ __('JPEG, PNG, WebP and AVIF images in enabled containers.') }}">
        <ui-card>
            <table class="w-full text-left">
                <thead>
                    <tr>
                        <th scope="col" class="pb-3">{{ __('Container') }}</th>
                        <th scope="col" class="pb-3">{{ __('Optimized / checked') }}</th>
                        <th scope="col" class="pb-3">{{ __('Pending') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($containers as $container)
                        <tr>
                            <th scope="row" class="py-2 font-normal">{{ $container['title'] }} ({{ $container['handle'] }})</th>
                            <td class="py-2">{{ number_format($container['optimized']) }}</td>
                            <td class="py-2">{{ number_format($container['pending']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-2">{{ __('No enabled asset containers are available.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            <form method="POST" action="{{ cp_route('utilities.tinify.optimize') }}" class="mt-6">
                @csrf
                <ui-button type="submit" variant="primary" @if (! $keyValid) disabled @endif>
                    {{ __('Optimize all pending images') }}
                </ui-button>
            </form>
            <p class="mt-3 text-sm">{{ __('Only images you can edit will be queued. Compression uses your Tinify quota. Resizing and conversion each cost an additional compression.') }}</p>
        </ui-card>
    </ui-panel>
</div>
