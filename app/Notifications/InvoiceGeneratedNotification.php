<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceGeneratedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Invoice $invoice) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $total    = number_format($this->invoice->patient_liability, 0, ',', '.');
        $dueDate  = $this->invoice->due_date;

        return (new MailMessage)
            ->subject("Invoice {$this->invoice->invoice_number} Diterbitkan")
            ->greeting("Halo, {$notifiable->name}!")
            ->line("Invoice untuk layanan Anda telah diterbitkan.")
            ->line("No. Invoice  : **{$this->invoice->invoice_number}**")
            ->line("Total Tagihan: **Rp {$total}**")
            ->line("Jatuh Tempo  : **{$dueDate}**")
            ->action('Bayar Sekarang', url(route('pasien.invoices.show', $this->invoice)))
            ->line('Silakan lakukan pembayaran sebelum jatuh tempo untuk menghindari keterlambatan layanan.')
            ->salutation('Salam, Tim Homecare');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'           => 'invoice.generated',
            'title'          => 'Invoice Baru Diterbitkan',
            'message'        => "Invoice {$this->invoice->invoice_number} sebesar Rp " .
                                number_format($this->invoice->patient_liability, 0, ',', '.') .
                                " jatuh tempo {$this->invoice->due_date}.",
            'invoice_id'     => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'total'          => $this->invoice->patient_liability,
            'due_date'       => $this->invoice->due_date,
            'url'            => route('pasien.invoices.show', $this->invoice),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
