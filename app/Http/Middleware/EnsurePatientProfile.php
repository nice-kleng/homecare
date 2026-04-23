<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pastikan pasien sudah mengisi data profil lengkap sebelum bisa memesan layanan.
 * Diletakkan di route group portal pasien, kecuali route profil itu sendiri.
 */
class EnsurePatientProfile
{
    /**
     * Route yang dikecualikan dari pengecekan ini.
     */
    private array $except = [
        'pasien.profile.edit',
        'pasien.profile.update',
        'pasien.profile.complete',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole('pasien')) {
            return $next($request);
        }

        // Lewati route yang dikecualikan
        if ($request->routeIs(...$this->except)) {
            return $next($request);
        }

        $patient = $user->patient;

        // Belum punya profil pasien sama sekali
        if (! $patient) {
            return redirect()->route('pasien.profile.complete')
                ->with('warning', 'Lengkapi profil Anda terlebih dahulu sebelum menggunakan layanan.');
        }

        // Profil ada tapi data wajib belum lengkap
        $required = ['name', 'gender', 'birth_date', 'address', 'phone'];
        foreach ($required as $field) {
            if (empty($patient->{$field})) {
                return redirect()->route('pasien.profile.edit')
                    ->with('warning', "Data profil belum lengkap. Mohon isi field '{$field}'.");
            }
        }

        return $next($request);
    }
}
