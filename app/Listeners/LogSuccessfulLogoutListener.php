<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;

class LogSuccessfulLogoutListener
{
    public function handle(Logout $event): void
    {
        if (! $event->user) {
            return;
        }

        try {
            ActivityLog::create([
                'user_id'     => $event->user->id,
                'action'      => 'logout',
                'ip_address'  => request()->ip(),
                'user_agent'  => request()->userAgent(),
                'description' => "Logout: {$event->user->email}",
            ]);
        } catch (\Throwable $e) {
            Log::warning('LogSuccessfulLogoutListener gagal: ' . $e->getMessage());
        }
    }
}
