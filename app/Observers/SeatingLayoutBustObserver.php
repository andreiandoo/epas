<?php

namespace App\Observers;

use App\Models\Seating\SeatingLayout;
use App\Services\Cache\AmbiletCacheBuster;
use Illuminate\Support\Facades\DB;

class SeatingLayoutBustObserver
{
    public function __construct(protected AmbiletCacheBuster $buster) {}

    public function saved(SeatingLayout $layout): void
    {
        $id = $layout->id;
        if (!$id) return;
        DB::afterCommit(function () use ($id) {
            $this->buster->bustLayout($id);
        });
    }
}
