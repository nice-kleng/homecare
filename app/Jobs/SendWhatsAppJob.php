<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int    $tries   = 3;
    public int    $backoff = 30;       // detik antar retry
    public int    $timeout = 15;       // timeout HTTP request

    public function __construct(
        public readonly string $phone,
        public readonly string $message,
        public readonly string $event   = '',
        public readonly int    $refId   = 0,
        public readonly string $refType = '',
    ) {
        $this->onQueue('whatsapp');
    }

    public function handle(): void
    {
        $provider = config('whatsapp.provider', 'fonnte');
        $endpoint = config('whatsapp.endpoint');
        $token    = config('whatsapp.token');

        if (empty($token) || empty($endpoint)) {
            Log::warning('WhatsApp tidak dikonfigurasi. Skip pengiriman.', [
                'phone' => $this->phone,
                'event' => $this->event,
            ]);
            return;
        }

        // Normalisasi nomor: hapus +, 0 di depan → 62
        $phone = $this->normalizePhone($this->phone);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['Authorization' => $token])
                ->post($endpoint, [
                    'target'  => $phone,
                    'message' => $this->message,
                ]);

            $success = $response->successful();
            $body    = $response->json();

            $this->writeLog($phone, $success, $body['id'] ?? null, $success ? '' : ($body['reason'] ?? 'Unknown error'));

            if (! $success) {
                Log::warning("WhatsApp gagal terkirim ke {$phone}. Response: " . $response->body());
                $this->fail(new \Exception("WhatsApp API error: " . $response->body()));
            }
        } catch (\Throwable $e) {
            $this->writeLog($phone, false, null, $e->getMessage());
            Log::error("SendWhatsAppJob exception: {$e->getMessage()}", ['phone' => $phone]);
            throw $e; // Lempar ulang agar queue melakukan retry
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendWhatsAppJob gagal setelah {$this->tries}x retry. Phone: {$this->phone}. Error: {$exception->getMessage()}");

        $this->writeLog($this->phone, false, null, $exception->getMessage());
    }

    // -----------------------------------------------------------------------

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        if (! str_starts_with($phone, '62')) {
            $phone = '62' . $phone;
        }

        return $phone;
    }

    private function writeLog(string $phone, bool $success, ?string $providerId, string $error): void
    {
        try {
            NotificationLog::create([
                'channel'           => 'whatsapp',
                'recipient'         => $phone,
                'subject'           => $this->event,
                'message'           => $this->message,
                'status'            => $success ? 'sent' : 'failed',
                'provider'          => config('whatsapp.provider', 'fonnte'),
                'provider_message_id'=> $providerId,
                'error_message'     => $error ?: null,
                'notifiable_type'   => $this->refType,
                'notifiable_id'     => $this->refId,
                'sent_at'           => $success ? now() : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Gagal menyimpan WhatsApp log: ' . $e->getMessage());
        }
    }
}
