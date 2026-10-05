<?php

namespace Tinify\Statamic\Fieldtypes;

use Statamic\Fields\Fieldtype;

class TinifyCompressLibrary extends Fieldtype
{
    protected $selectable = false;

    protected $localizable = false;

    protected $validatable = false;

    protected $defaultable = false;

    public function preload(): array
    {
        return ['url' => cp_route('tinify.compress-library')];
    }
}
