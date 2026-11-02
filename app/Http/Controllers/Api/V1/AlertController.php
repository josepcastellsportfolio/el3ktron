<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AlertResource;
use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AlertController extends Controller
{
    /**
     * List alerts, open ones by default, newest first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'all' => ['nullable', 'boolean'],
            'type' => ['nullable', 'string'],
        ]);

        $alerts = Alert::query()
            ->when(! ($filters['all'] ?? false), fn ($q) => $q->open())
            ->when($filters['type'] ?? null, fn ($q, string $type) => $q->where('type', $type))
            ->latest('updated_at')
            ->paginate(50);

        return AlertResource::collection($alerts);
    }

    /**
     * Mark an alert as handled.
     */
    public function resolve(Alert $alert): AlertResource
    {
        $alert->update(['resolved_at' => $alert->resolved_at ?? now()]);

        return new AlertResource($alert);
    }
}
