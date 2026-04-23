<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class GeneratePdfReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 120;

    public function __construct(
        public readonly string $reportType,  // 'visits' | 'revenue' | 'staff'
        public readonly string $from,
        public readonly string $to,
        public readonly int    $requestedBy, // user_id yang meminta
    ) {
        $this->onQueue('reports');
    }

    public function handle(ReportService $reportService): void
    {
        $from = Carbon::parse($this->from);
        $to   = Carbon::parse($this->to);

        $data     = $reportService->getDashboardData('custom');
        $filename = "report-{$this->reportType}-{$from->format('Ymd')}-{$to->format('Ymd')}.pdf";
        $path     = "reports/{$filename}";

        $view = match ($this->reportType) {
            'revenue' => 'pdf.reports.revenue',
            'staff'   => 'pdf.reports.staff',
            default   => 'pdf.reports.visits',
        };

        $pdf = Pdf::loadView($view, array_merge($data, [
            'from'        => $from,
            'to'          => $to,
            'generated_at'=> now(),
        ]))->setPaper('a4', 'landscape');

        Storage::disk('public')->put($path, $pdf->output());

        Log::info("PDF report generated: {$path}");

        // Notifikasi ke user yang meminta bahwa laporan sudah siap
        $user = User::find($this->requestedBy);
        if ($user) {
            $user->notify(new \Illuminate\Notifications\Notification());

            // Kirim email dengan link download
            \Illuminate\Support\Facades\Mail::raw(
                "Laporan {$this->reportType} ({$from->format('d M Y')} - {$to->format('d M Y')}) sudah siap. "
                . "Download: " . Storage::disk('public')->url($path),
                fn($msg) => $msg->to($user->email)
                                ->subject("Laporan {$this->reportType} Siap Didownload")
            );
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("GeneratePdfReportJob gagal. Type: {$this->reportType}. Error: {$exception->getMessage()}");
    }
}
