<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * Redirect user ke dashboard sesuai role setelah login berhasil.
     */
    public function toResponse($request)
    {
        $user = $request->user();

        $redirectUrl = match (true) {
            $user->hasRole('admin')   => route('admin.dashboard'),
            $user->hasRole('petugas') => route('petugas.dashboard'),
            $user->hasRole('dokter')  => route('dokter.dashboard'),
            $user->hasRole('pasien')  => route('pasien.dashboard'),
            default                   => route('dashboard'),
        };

        return $request->wantsJson()
            ? response()->json(['two_factor' => false])
            : redirect()->intended($redirectUrl);
    }
}
