<?php

namespace App\Services;

use App\Repositories\ReportRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\VisitReportExport;
use App\Exports\RevenueReportExport;

class ReportService
{
    public function __construct(
        protected ReportRepository $reportRepo,
    ) {}

    /**
     * Ringkasan laporan untuk dashboard admin.
     */
    public function getDashboardData(string $period = 'monthly'): array
    {
        [$from, $to] = $this->resolvePeriod($period);

        return [
            'summary'          => $this->reportRepo->summary($from, $to),
            'revenue_chart'    => $this->reportRepo->revenueByPeriod($from, $to, $period === 'yearly' ? 'month' : 'day'),
            'top_services'     => $this->reportRepo->visitsByService($from, $to)->take(5),
            'staff_performance'=> $this->reportRepo->staffPerformance($from, $to)->take(10),
            'period'           => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ];
    }

    /**
     * Export laporan kunjungan ke Excel (menggunakan maatwebsite/excel).
     */
    public function exportVisitsExcel(Carbon $from, Carbon $to): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $filename = 'laporan-kunjungan-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.xlsx';
        return Excel::download(new VisitReportExport($from, $to), $filename);
    }

    /**
     * Export laporan pendapatan ke Excel.
     */
    public function exportRevenueExcel(Carbon $from, Carbon $to): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $filename = 'laporan-pendapatan-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.xlsx';
        return Excel::download(new RevenueReportExport($from, $to), $filename);
    }

    /**
     * Export laporan kunjungan ke PDF.
     */
    public function exportVisitsPdf(Carbon $from, Carbon $to): \Illuminate\Http\Response
    {
        $data = [
            'visits'  => $this->reportRepo->visitsByService($from, $to),
            'summary' => $this->reportRepo->summary($from, $to),
            'from'    => $from,
            'to'      => $to,
        ];

        return Pdf::loadView('pdf.reports.visits', $data)
                  ->setPaper('a4', 'landscape')
                  ->download('laporan-kunjungan-' . $from->format('Ymd') . '.pdf');
    }

    /**
     * Laporan performa petugas dalam format siap tampil.
     */
    public function staffReport(Carbon $from, Carbon $to): array
    {
        $performance = $this->reportRepo->staffPerformance($from, $to);

        return $performance->map(function ($row) {
            $completionRate = $row->total_visits > 0
                ? round($row->completed / $row->total_visits * 100, 1)
                : 0;

            return array_merge((array) $row, [
                'completion_rate' => $completionRate,
                'avg_rating'      => $row->avg_rating ? round($row->avg_rating, 1) : '-',
            ]);
        })->toArray();
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function resolvePeriod(string $period): array
    {
        return match ($period) {
            'weekly'  => [now()->startOfWeek(), now()->endOfWeek()],
            'yearly'  => [now()->startOfYear(), now()->endOfYear()],
            'today'   => [Carbon::today(), Carbon::today()],
            default   => [now()->startOfMonth(), now()->endOfMonth()], // monthly
        };
    }
}
