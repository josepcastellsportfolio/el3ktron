<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\SyncCatalog;
use App\Models\SyncRun;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    /**
     * Queue a catalogue sync now, instead of waiting for the schedule.
     */
    public function catalog(): JsonResponse
    {
        SyncCatalog::dispatch();

        return response()->json(['queued' => true], 202);
    }

    /**
     * The latest sync runs, newest first.
     */
    public function runs(): JsonResponse
    {
        return response()->json(SyncRun::latest('started_at')->limit(20)->get());
    }
}
