<?php

namespace App\Listeners;

use App\Events\PaymentVerified;
use App\Jobs\SendWhatsAppJob;
use App\Notifications\PaymentVerifiedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyPatientPaymentVerifiedListener implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(PaymentVerified $event): void
    {
        $payment = $event->payment;
        $invoice = $event->invoice->load('patient.user');
        $patient = $invoice->patient;

        // In-app + email
        if ($patient->user) {
            $patient->user->notify(new PaymentVerifiedNotification($payment, $invoice));
        }

        // WhatsApp
        if ($patient->phone) {
            $amount = number_format($payment->amount, 0, ',', '.');

            SendWhatsAppJob::dispatch(
                phone  : $patient->phone,
                message: "Halo {$patient->name},\n\n"
                         . "Pembayaran Anda telah *terverifikasi*.\n"
                         . "Invoice : {$invoice->invoice_number}\n"
                         . "Jumlah  : Rp {$amount}\n"
                         . "Status  : " . ($invoice->status === 'paid' ? '*Lunas*' : 'Dibayar Sebagian') . "\n\n"
                         . "Terima kasih atas kepercayaan Anda.",
                event  : 'payment.verified',
                refId  : $payment->id,
                refType: 'payment',
            );
        }
    }
}
