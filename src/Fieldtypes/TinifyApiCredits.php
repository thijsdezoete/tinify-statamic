<?php

namespace ThijsDeZoete\TinifyStatamic\Fieldtypes;

use Statamic\Fields\Fieldtype;

class TinifyApiCredits extends Fieldtype
{
    protected $selectable = false;

    protected $localizable = false;

    protected $validatable = false;

    protected $defaultable = false;

    public function preload(): array
    {
        return ['url' => cp_route('tinify.account-usage')];
    }
}
