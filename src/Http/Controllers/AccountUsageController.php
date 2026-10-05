<?php

namespace Tinify\Statamic\Http\Controllers;

use Statamic\Facades\Addon;
use Statamic\Http\Controllers\CP\CpController;
use Tinify\Statamic\Api\Client;

class AccountUsageController extends CpController
{
    public function __invoke(Client $client): array
    {
        $this->authorize('editSettings', Addon::get('tinify/statamic'));

        return $client->accountUsage();
    }
}
