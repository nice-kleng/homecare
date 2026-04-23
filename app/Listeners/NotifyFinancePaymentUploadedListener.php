<?php

namespace App\Listeners;

use App\Events\PaymentUploaded;
use App\Models\User;
use App\Notifications\NewOrderAdminNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class NotifyFinancePaymentUploadedListener implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(PaymentUploaded $event): void
    {
        $payment = $event->payment;
        $invoice = $event->invoice->load('patient');
        $amount  = number_format($payment->amount, 0, ',', '.');

        // Notifikasi ke semua admin & finance (role admin)
        User::role('admin')
            ->where('status', 'active')
            ->get()
            ->each(function ($admin) use ($payment, $invoice, $amount) {
                $admin->notify(new class($payment, $invoice, $amount) extends Notification implements ShouldQueue {
                    use Queueable;

                    public function __construct(
                        private $payment,
                        private $invoice,
                        private string $amount,
                    ) {}

                    public function via($notifiable): array
                    {
                        return ['database', 'mail'];
                    }

                    public function toMail($notifiable): MailMessage
                    {
                        return (new MailMessage)
                            ->subject("[Finance] Bukti Pembayaran Diupload - {$this->invoice->invoice_number}")
                            ->greeting("Halo, {$notifiable->name}!")
                            ->line("Pasien **{$this->invoice->patient->name}** mengupload bukti pembayaran.")
                            ->line("Invoice  : {$this->invoice->invoice_number}")
                            ->line("Jumlah   : Rp {$this->amount}")
                            ->line("Metode   : {$this->payment->payment_method}")
                            ->action('Verifikasi Sekarang', url(route('admin.payments.show', $this->payment)));
                    }

                    public function toDatabase($notifiable): array
                    {
                        return [
                            'type'           => 'payment.uploaded',
                            'title'          => 'Bukti Pembayaran Perlu Diverifikasi',
                            'message'        => "Pembayaran Rp {$this->amount} dari {$this->invoice->patient->name} menunggu verifikasi.",
                            'payment_id'     => $this->payment->id,
                            'invoice_id'     => $this->invoice->id,
                            'invoice_number' => $this->invoice->invoice_number,
                            'url'            => route('admin.payments.show', $this->payment),
                            'priority'       => 'high',
                        ];
                    }

                    public function toArray($notifiable): array
                    {
                        return $this->toDatabase($notifiable);
                    }
                });
            });

        Log::info("Notifikasi payment upload dikirim ke admin. Payment ID: {$payment->id}");
    }
}
