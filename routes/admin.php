<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PatientController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\VisitController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

// Manajemen Order
Route::prefix('orders')->name('orders.')->group(function () {
    Route::get('/', [OrderController::class, 'index'])->name('index');
    Route::get('/{order}', [OrderController::class, 'show'])->name('show');
    Route::post('/{order}/confirm', [OrderController::class, 'confirm'])->name('confirm');
    Route::post('/{order}/assign', [OrderController::class, 'assign'])->name('assign');
    Route::post('/{order}/cancel', [OrderController::class, 'cancel'])->name('cancel');
    Route::post('/{order}/reschedule', [OrderController::class, 'reschedule'])->name('reschedule');
    Route::post('/{order}/invoice', [OrderController::class, 'generateInvoice'])->name('generate-invoice');
});

// Manajemen Petugas (Staff)
Route::prefix('staff')->name('staff.')->group(function () {
    Route::get('/', [StaffController::class, 'index'])->name('index');
    Route::get('/create', [StaffController::class, 'create'])->name('create');
    Route::post('/', [StaffController::class, 'store'])->name('store');
    Route::get('/{staff}', [StaffController::class, 'show'])->name('show');
    Route::get('/{staff}/edit', [StaffController::class, 'edit'])->name('edit');
    Route::put('/{staff}', [StaffController::class, 'update'])->name('update');
    Route::post('/leaves/{leave}/approve', [StaffController::class, 'approveLeave'])->name('leaves.approve');
});

// Manajemen Pasien
Route::prefix('patients')->name('patients.')->group(function () {
    Route::get('/', [PatientController::class, 'index'])->name('index');
    Route::get('/{patient}', [PatientController::class, 'show'])->name('show');
    Route::get('/{patient}/edit', [PatientController::class, 'edit'])->name('edit');
    Route::put('/{patient}', [PatientController::class, 'update'])->name('update');
    Route::post('/{patient}/deactivate', [PatientController::class, 'deactivate'])->name('deactivate');
    Route::post('/{patient}/activate', [PatientController::class, 'activate'])->name('activate');
});

// Penjadwalan & Kunjungan
Route::get('/schedules', [ScheduleController::class, 'index'])->name('schedules.index');
Route::get('/schedules/available-staff', [ScheduleController::class, 'availableStaff'])->name('schedules.available-staff');
Route::get('/schedules/workload', [ScheduleController::class, 'workload'])->name('schedules.workload');

Route::prefix('visits')->name('visits.')->group(function () {
    Route::get('/', [VisitController::class, 'index'])->name('index');
    Route::get('/{visit}', [VisitController::class, 'show'])->name('show');
    Route::post('/{visit}/validate', [VisitController::class, 'validate'])->name('validate');
});

// Keuangan (Invoices & Payments)
Route::prefix('invoices')->name('invoices.')->group(function () {
    Route::get('/', [InvoiceController::class, 'index'])->name('index');
    Route::get('/{invoice}', [InvoiceController::class, 'show'])->name('show');
    Route::get('/{invoice}/download', [InvoiceController::class, 'downloadPdf'])->name('download');

    // Verifikasi Pembayaran
    Route::get('/payments/{payment}/verify', [InvoiceController::class, 'showPayment'])->name('payments.show');
    Route::post('/payments/{payment}/verify', [InvoiceController::class, 'verifyPayment'])->name('payments.verify');
    Route::post('/payments/{payment}/reject', [InvoiceController::class, 'rejectPayment'])->name('payments.reject');
});

// Laporan
Route::prefix('reports')->name('reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('/export/visits', [ReportController::class, 'exportVisitsExcel'])->name('export.visits');
    Route::get('/export/revenue', [ReportController::class, 'exportRevenueExcel'])->name('export.revenue');
    Route::post('/export/pdf', [ReportController::class, 'exportPdf'])->name('export.pdf');
});

// Master Data Layanan
Route::resource('services', ServiceController::class)->except(['show']);

// Pengaturan Aplikasi
Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
Route::patch('/settings', [SettingController::class, 'update'])->name('settings.update');
