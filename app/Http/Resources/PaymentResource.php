<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'payment_number' => $this->payment_number,
            'amount'         => $this->amount,
            'payment_date'   => $this->payment_date,
            'payment_method' => $this->payment_method,
            'method_label'   => $this->methodLabel(),
            'status'         => $this->status,
            'status_label'   => match ($this->status) {
                'pending'  => 'Menunggu Verifikasi',
                'verified' => 'Terverifikasi',
                'rejected' => 'Ditolak',
                default    => ucfirst($this->status),
            },

            // Detail transfer
            'bank_name'          => $this->bank_name,
            'account_number'     => $this->account_number,
            'transfer_reference' => $this->transfer_reference,

            // Bukti pembayaran
            'proof_file'  => $this->proof_file
                ? asset("storage/{$this->proof_file}")
                : null,

            // Verifikasi (hanya untuk admin/finance)
            'verification' => $this->when(
                $request->user()?->hasRole('admin'),
                [
                    'verified_by'   => $this->whenLoaded('verifiedBy', fn() => $this->verifiedBy?->name),
                    'verified_at'   => $this->verified_at,
                    'notes'         => $this->verification_notes,
                ]
            ),

            'notes'      => $this->notes,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }

    private function methodLabel(): string
    {
        return match ($this->payment_method) {
            'cash'         => 'Tunai',
            'transfer_bank'=> 'Transfer Bank',
            'bpjs'         => 'BPJS',
            'asuransi'     => 'Asuransi',
            'qris'         => 'QRIS',
            'kartu_debit'  => 'Kartu Debit',
            'kartu_kredit' => 'Kartu Kredit',
            default        => ucfirst($this->payment_method),
        };
    }
}
