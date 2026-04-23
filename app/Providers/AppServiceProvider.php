<?php

namespace App\Providers;

use App\Actions\AssignStaffAction;
use App\Actions\CancelOrderAction;
use App\Actions\CompleteVisitAction;
use App\Actions\CreateOrderAction;
use App\Actions\GenerateInvoiceAction;
use App\Actions\VerifyPaymentAction;
use App\Repositories\OrderRepository;
use App\Repositories\PatientRepository;
use App\Repositories\ReportRepository;
use App\Repositories\StaffRepository;
use App\Services\InvoiceService;
use App\Services\MedicalRecordService;
use App\Services\NotificationService;
use App\Services\OrderService;
use App\Services\ReportService;
use App\Services\SchedulingService;
use App\Services\VisitService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ---- Repositories (singleton: satu instance selama request) ----
        $this->app->singleton(OrderRepository::class);
        $this->app->singleton(StaffRepository::class);
        $this->app->singleton(PatientRepository::class);
        $this->app->singleton(ReportRepository::class);

        // ---- Services ----
        $this->app->singleton(NotificationService::class);
        $this->app->singleton(SchedulingService::class);

        $this->app->singleton(OrderService::class, fn($app) => new OrderService(
            $app->make(OrderRepository::class),
            $app->make(StaffRepository::class),
            $app->make(NotificationService::class),
        ));

        $this->app->singleton(VisitService::class, fn($app) => new VisitService(
            $app->make(NotificationService::class),
            $app->make(StaffRepository::class),
        ));

        $this->app->singleton(InvoiceService::class, fn($app) => new InvoiceService(
            $app->make(NotificationService::class),
        ));

        $this->app->singleton(MedicalRecordService::class, fn($app) => new MedicalRecordService(
            $app->make(PatientRepository::class),
        ));

        $this->app->singleton(ReportService::class, fn($app) => new ReportService(
            $app->make(ReportRepository::class),
        ));

        // ---- Actions (transient: instance baru tiap dipanggil) ----
        $this->app->bind(CreateOrderAction::class, fn($app) => new CreateOrderAction(
            $app->make(OrderService::class),
        ));

        $this->app->bind(AssignStaffAction::class, fn($app) => new AssignStaffAction(
            $app->make(OrderService::class),
            $app->make(SchedulingService::class),
        ));

        $this->app->bind(CompleteVisitAction::class, fn($app) => new CompleteVisitAction(
            $app->make(VisitService::class),
            $app->make(InvoiceService::class),
            $app->make(MedicalRecordService::class),
        ));

        $this->app->bind(VerifyPaymentAction::class, fn($app) => new VerifyPaymentAction(
            $app->make(InvoiceService::class),
        ));

        $this->app->bind(GenerateInvoiceAction::class, fn($app) => new GenerateInvoiceAction(
            $app->make(InvoiceService::class),
        ));

        $this->app->bind(CancelOrderAction::class, fn($app) => new CancelOrderAction(
            $app->make(OrderService::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
         // Helper global untuk baca settings dari tabel settings
        if (! function_exists('setting')) {
            function setting(string $key, mixed $default = null): mixed
            {
                static $cache = [];

                if (! isset($cache[$key])) {
                    try {
                        $setting = \App\Models\Setting::where('key', $key)->first();
                        $cache[$key] = $setting ? $setting->value : $default;
                    } catch (\Throwable) {
                        return $default;
                    }
                }

                return $cache[$key] ?? $default;
            }
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
