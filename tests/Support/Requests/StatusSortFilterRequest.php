<?php

namespace Tests\Support\Requests;

use App\Http\Requests\ListFilterRequest;

class StatusSortFilterRequest extends ListFilterRequest
{
    protected function statusValues(): array
    {
        return ['active', 'inactive'];
    }

    protected function sortable(): array
    {
        return ['name', 'created_at'];
    }
}
