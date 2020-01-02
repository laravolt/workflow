<?php

declare(strict_types=1);

namespace Laravolt\Camunda\Entities;

use Spatie\DataTransferObject\DataTransferObject;

class Multirow extends DataTransferObject
{
    /** @var string */
    public $key;

    /** @var array */
    public $data;
}
