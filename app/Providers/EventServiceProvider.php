<?php

namespace App\Providers;

use App\Events\OrderCancelled;
use App\Events\OrderConfirmed;
use App\Events\OrderCreated;
use App\Events\PaymentUploaded;
use App\Events\PaymentVerified;
use App\Events\StaffAssigned;
use App\Events\VisitCompleted;
use App\Events\VisitNoShow;
use App\Events\VisitStarted;
use App\Listeners\GenerateInvoiceListener;
use App\Listeners\LogOrderCancelledListener;
use App\Listeners\NotifyDoctorForValidationListener;
use App\Listeners\NotifyFinancePaymentUploadedListener;
use App\Listeners\NotifyPatientOrderConfirmedListener;
use App\Listeners\NotifyPatientPaymentVerifiedListener;
use App\Listeners\NotifyStaffAssignedListener;
use App\Listeners\SendOrderConfirmationListener;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Event → Listener mapping.
     * Setiap event bisa punya banyak listener yang berjalan secara async (queue).
     */
    protected $listen = [

        // ----------------------------------------------------------------
        // Order events
        // ----------------------------------------------------------------
        OrderCreated::class => [
            SendOrderConfirmationListener::class,   // notif admin + WA ke pasien
        ],

        OrderConfirmed::class => [
            NotifyPatientOrderConfirmedListener::class, // notif pasien
        ],

        OrderCancelled::class => [
            LogOrderCancelledListener::class,       // notif pasien + petugas + log
        ],

        StaffAssigned::class => [
            NotifyStaffAssignedListener::class,     // notif petugas & pasien
        ],

        // ----------------------------------------------------------------
        // Visit events
        // ----------------------------------------------------------------
        VisitStarted::class => [
            // Kosong untuk saat ini — bisa ditambah listener tracking nanti
        ],

        VisitCompleted::class => [
            GenerateInvoiceListener::class,             // generate invoice otomatis
            NotifyDoctorForValidationListener::class,   // notif dokter untuk validasi
        ],

        VisitNoShow::class => [
            // Bisa ditambah: notif admin, penalti petugas, dll
        ],

        // ----------------------------------------------------------------
        // Payment events
        // ----------------------------------------------------------------
        PaymentUploaded::class => [
            NotifyFinancePaymentUploadedListener::class, // notif admin/finance
        ],

        PaymentVerified::class => [
            NotifyPatientPaymentVerifiedListener::class, // notif pasien
        ],

        // ----------------------------------------------------------------
        // Auth events (built-in Laravel)
        // ----------------------------------------------------------------
        Login::class => [
            \App\Listeners\LogSuccessfulLoginListener::class,
        ],

        Logout::class => [
            \App\Listeners\LogSuccessfulLogoutListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be auto-discovered.
     * Matikan auto-discover agar mapping di atas yang dipakai (lebih eksplisit).
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
