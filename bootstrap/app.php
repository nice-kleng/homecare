<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Load route file per role
            \Illuminate\Support\Facades\Route::middleware('web')
                ->prefix('admin')
                ->name('admin.')
                ->middleware(['auth', 'role:admin'])
                ->group(base_path('routes/admin.php'));

            \Illuminate\Support\Facades\Route::middleware('web')
                ->prefix('petugas')
                ->name('petugas.')
                ->middleware(['auth', 'role:petugas', 'staff.active'])
                ->group(base_path('routes/petugas.php'));

            \Illuminate\Support\Facades\Route::middleware('web')
                ->prefix('pasien')
                ->name('pasien.')
                ->middleware(['auth', 'role:pasien', 'patient.profile'])
                ->group(base_path('routes/pasien.php'));

            \Illuminate\Support\Facades\Route::middleware('web')
                ->prefix('dokter')
                ->name('dokter.')
                ->middleware(['auth', 'role:dokter'])
                ->group(base_path('routes/dokter.php'));
        }
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

         // ---- Global middleware (berjalan di semua request) ----
        $middleware->append(SetLocale::class);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'patient.profile' => \App\Http\Middleware\EnsurePatientProfile::class,
            'staff.active' => \App\Http\Middleware\CheckStaffStatus::class,
        ]);

        // ---- Throttle untuk API ----
        $middleware->throttleApi();
    })
    ->withSchedule(function (Schedule $schedule): void {
        // ----------------------------------------------------------------
        // Kirim reminder kunjungan H-1 setiap hari jam 18.00
        // ----------------------------------------------------------------
        $schedule->command('homecare:send-reminders')
                 ->dailyAt('18:00')
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->appendOutputTo(storage_path('logs/reminders.log'));

        // ----------------------------------------------------------------
        // Auto-cancel order pending > 24 jam, setiap hari jam 01.00
        // ----------------------------------------------------------------
        $schedule->command('homecare:auto-cancel --hours=24')
                 ->dailyAt('01:00')
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->appendOutputTo(storage_path('logs/auto-cancel.log'));

        // ----------------------------------------------------------------
        // Tandai invoice overdue setiap hari jam 00.05
        // ----------------------------------------------------------------
        $schedule->command('homecare:mark-overdue-invoices')
                 ->dailyAt('00:05')
                 ->withoutOverlapping()
                 ->runInBackground();

        // ----------------------------------------------------------------
        // Generate laporan harian & kirim ke admin setiap hari jam 07.00
        // ----------------------------------------------------------------
        $schedule->command('homecare:daily-report --type=all')
                 ->dailyAt('07:00')
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->appendOutputTo(storage_path('logs/daily-report.log'));

        // ----------------------------------------------------------------
        // Bersihkan log notifikasi > 90 hari, setiap minggu hari Senin
        // ----------------------------------------------------------------
        $schedule->call(function () {
            \App\Models\NotificationLog::where('created_at', '<', now()->subDays(90))->delete();
            \Illuminate\Support\Facades\Log::info('NotificationLog cleanup selesai.');
        })->weekly()->mondays()->at('02:00')->name('cleanup-notification-logs');

        // ----------------------------------------------------------------
        // Bersihkan activity log > 180 hari, setiap bulan tanggal 1
        // ----------------------------------------------------------------
        // $schedule->call(function () {
        //     \App\Models\ActivityLog::where('created_at', '<', now()->subDays(180))->delete();
        // })->monthly()->name('cleanup-activity-logs');

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render ValidationException sebagai JSON untuk API request
        $exceptions->render(function (
            \Illuminate\Validation\ValidationException $e,
            Request $request
        ) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Data tidak valid.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        // Render AuthorizationException
        $exceptions->render(function (
            \Illuminate\Auth\Access\AuthorizationException $e,
            Request $request
        ) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Anda tidak memiliki izin untuk tindakan ini.',
                ], 403);
            }
        });

        // Render ModelNotFoundException
        $exceptions->render(function (
            \Illuminate\Database\Eloquent\ModelNotFoundException $e,
            Request $request
        ) {
            if ($request->expectsJson()) {
                $model = class_basename($e->getModel());
                return response()->json([
                    'message' => "{$model} tidak ditemukan.",
                ], 404);
            }
        });

        // Render custom business logic exception
        $exceptions->render(function (
            \Exception $e,
            Request $request
        ) {
            if ($request->expectsJson() && $e->getCode() >= 400) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], $e->getCode());
            }
        });
    })->create();
