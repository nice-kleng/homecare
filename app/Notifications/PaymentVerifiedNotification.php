<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentVerifiedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Payment $payment,
        public readonly Invoice $invoice,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format($this->payment->amount, 0, ',', '.');

        return (new MailMessage)
            ->subject("Pembayaran Terverifikasi - {$this->invoice->invoice_number}")
            ->greeting("Halo, {$notifiable->name}!")
            ->line("Pembayaran Anda telah berhasil diverifikasi.")
            ->line("No. Invoice  : **{$this->invoice->invoice_number}**")
            ->line("Jumlah Bayar : **Rp {$amount}**")
            ->line("Metode       : {$this->payment->methodLabel()}")
            ->line("Tanggal      : {$this->payment->payment_date}")
            ->line("Status Invoice: **{$this->invoice->status_label}**")
            ->action('Lihat Invoice', url(route('pasien.invoices.show', $this->invoice)))
            ->salutation('Terima kasih, Tim Homecare');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'           => 'payment.verified',
            'title'          => 'Pembayaran Terverifikasi',
            'message'        => "Pembayaran Rp " .
                                number_format($this->payment->amount, 0, ',', '.') .
                                " untuk invoice {$this->invoice->invoice_number} telah terverifikasi.",
            'payment_id'     => $this->payment->id,
            'invoice_id'     => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'amount'         => $this->payment->amount,
            'url'            => route('pasien.invoices.show', $this->invoice),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
