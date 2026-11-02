<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\SyncRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    /**
     * Liveness for the load balancer and monitoring: database reachable,
     * age of the last good catalogue sync, open critical alerts. 503 when the
     * database is down.
     */
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('SELECT 1');
        } catch (Throwable) {
            return response()->json(['status' => 'down', 'database' => false], 503);
        }

        $lastSync = SyncRun::where('status', 'succeeded')->max('finished_at');

        return response()->json([
            'status' => 'ok',
            'database' => true,
            'last_catalog_sync' => $lastSync,
            'open_critical_alerts' => Alert::open()->where('severity', 'critical')->count(),
        ]);
    }
}
