<?php

namespace ThijsDeZoete\TinifyStatamic\Http\Controllers;

use Statamic\Facades\Addon;
use Statamic\Http\Controllers\CP\CpController;
use ThijsDeZoete\TinifyStatamic\Api\Client;

class AccountUsageController extends CpController
{
    public function __invoke(Client $client): array
    {
        $this->authorize('editSettings', Addon::get('thijsdezoete/tinify-statamic'));

        return $client->accountUsage();
    }
}
