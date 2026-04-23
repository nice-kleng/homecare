<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GeneratePdfReportJob;
use App\Repositories\ReportRepository;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService    $reportService,
        protected ReportRepository $reportRepo,
    ) {}

    public function index(Request $request): Response
    {
        $period = $request->input('period', 'monthly');
        $from   = $request->input('from')
            ? Carbon::parse($request->input('from'))
            : Carbon::now()->startOfMonth();
        $to     = $request->input('to')
            ? Carbon::parse($request->input('to'))
            : Carbon::now()->endOfMonth();

        return Inertia::render('Admin/Reports/Index', [
            'summary'     => $this->reportRepo->summary($from, $to),
            'revenue'     => $this->reportRepo->revenueByPeriod($from, $to, $period === 'yearly' ? 'month' : 'day'),
            'byService'   => $this->reportRepo->visitsByService($from, $to),
            'staffReport' => $this->reportService->staffReport($from, $to),
            'filters'     => [
                'period' => $period,
                'from'   => $from->toDateString(),
                'to'     => $to->toDateString(),
            ],
        ]);
    }

    /**
     * Export kunjungan ke Excel — langsung download.
     */
    public function exportVisitsExcel(Request $request): BinaryFileResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        return $this->reportService->exportVisitsExcel(
            Carbon::parse($request->from),
            Carbon::parse($request->to),
        );
    }

    /**
     * Export pendapatan ke Excel.
     */
    public function exportRevenueExcel(Request $request): BinaryFileResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        return $this->reportService->exportRevenueExcel(
            Carbon::parse($request->from),
            Carbon::parse($request->to),
        );
    }

    /**
     * Export ke PDF — diproses di background, dikirim via email.
     */
    public function exportPdf(Request $request): RedirectResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
            'type' => ['required', 'in:visits,revenue,staff'],
        ]);

        GeneratePdfReportJob::dispatch(
            reportType : $request->type,
            from       : $request->from,
            to         : $request->to,
            requestedBy: auth()->id(),
        );

        return back()->with(
            'success',
            'Laporan PDF sedang diproses dan akan dikirim ke email Anda dalam beberapa menit.'
        );
    }
}
