<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Petugas dengan status non-aktif / cuti / off_duty tidak bisa mengakses
 * fitur operasional (check-in, input SOAP, dll).
 * Hanya untuk route group petugas.
 */
class CheckStaffStatus
{
    private array $allowedStatuses = ['active'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole('petugas')) {
            return $next($request);
        }

        $staff = $user->staff;

        if (! $staff) {
            abort(403, 'Data petugas tidak ditemukan. Hubungi administrator.');
        }

        if (! in_array($staff->status, $this->allowedStatuses)) {
            $label = match ($staff->status) {
                'inactive' => 'tidak aktif',
                'cuti'     => 'sedang cuti',
                'off_duty' => 'tidak bertugas',
                default    => $staff->status,
            };

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => "Akun petugas Anda saat ini {$label}.",
                    'status'  => $staff->status,
                ], 403);
            }

            return redirect()->route('petugas.dashboard')
                ->with('error', "Akun Anda {$label}. Hubungi administrator untuk informasi lebih lanjut.");
        }

        return $next($request);
    }
}
