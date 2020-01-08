<?php

namespace Laravolt\Camunda\Services\FormAdapter\Fields;

use Laravolt\Camunda\Services\FormAdapter\FieldAdapter;

class HtmlAdapter extends FieldAdapter
{
    protected $type = 'html';

    public function toArray()
    {
        $schema = parent::toArray();
        $schema['content'] = $this->field->field_select_query;

        return $schema;
    }
}
