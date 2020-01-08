<?php

declare(strict_types=1);

namespace Laravolt\Camunda\FieldFormatter;

use Illuminate\Support\Arr;
use Laravolt\Camunda\FieldFormatter\Camunda\CamundaFormatterFactory;

class CamundaFormatter
{
    public static function format($data, $fields)
    {
        $formattedData = [];
        foreach ($fields as $field) {
            $formattedData[$field['field_name']] = CamundaFormatterFactory::make(
                $field['field_name'],
                Arr::get($data, $field['field_name']),
                $field['field_type']
            );
        }

        return $formattedData;
    }
}