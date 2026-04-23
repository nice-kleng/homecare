<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class LogSuccessfulLoginListener
{
    /**
     * Tidak di-queue — harus sync agar session & IP tercatat akurat.
     */
    public function handle(Login $event): void
    {
        try {
            ActivityLog::create([
                'user_id'     => $event->user->id,
                'action'      => 'login',
                'ip_address'  => request()->ip(),
                'user_agent'  => request()->userAgent(),
                'description' => "Login berhasil: {$event->user->email}",
            ]);
        } catch (\Throwable $e) {
            Log::warning('LogSuccessfulLoginListener gagal: ' . $e->getMessage());
        }
    }
}
