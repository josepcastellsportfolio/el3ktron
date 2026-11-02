<?php

namespace App\Alerts;

use App\Enums\AlertSeverity;
use App\Mail\AlertRaised;
use App\Models\Alert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The one way the system says something went wrong. An alert is stored (the
 * API lists it), logged, and mailed if an address is configured.
 *
 * Raising the same `dedupeKey` again while the first is still open only
 * bumps it, so a sync failing every five minutes sends one mail, not 288.
 */
final class AlertService
{
    /** @param  array<string, mixed>  $context */
    public function raise(string $type, AlertSeverity $severity, string $dedupeKey, string $message, array $context = []): Alert
    {
        $open = Alert::open()->where('dedupe_key', $dedupeKey)->first();
        if ($open !== null) {
            $open->forceFill(['context' => $context ?: $open->context])->touch();

            return $open;
        }

        $alert = Alert::create([
            'type' => $type,
            'severity' => $severity,
            'dedupe_key' => $dedupeKey,
            'message' => $message,
            'context' => $context,
        ]);

        $this->notify($alert);

        return $alert;
    }

    /** Closes the open alert with this key, if any: the condition went away. */
    public function resolve(string $dedupeKey): void
    {
        Alert::open()->where('dedupe_key', $dedupeKey)->update(['resolved_at' => now()]);
    }

    private function notify(Alert $alert): void
    {
        $level = $alert->severity === AlertSeverity::Critical ? 'critical' : 'warning';
        Log::log($level, "[alert:{$alert->type}] {$alert->message}", $alert->context ?? []);

        $to = config('el3ktron.alerts.mail_to');
        if (! $to) {
            return;
        }
        Mail::to($to)->send(new AlertRaised($alert));
    }
}
