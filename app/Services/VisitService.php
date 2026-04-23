<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Visit;
use App\Repositories\StaffRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VisitService
{
    public function __construct(
        protected NotificationService $notificationService,
        protected StaffRepository     $staffRepo,
    ) {}

    /**
     * Petugas memulai perjalanan ke lokasi pasien.
     */
    public function startJourney(Visit $visit): Visit
    {
        $visit->update([
            'status'      => 'on_the_way',
            'departed_at' => now(),
        ]);

        $this->notificationService->notifyPatient(
            $visit->order->patient,
            'visit.on_the_way',
            $visit->order
        );

        activity()->performedOn($visit)->causedBy(Auth::user())->log('Petugas berangkat');

        return $visit->fresh();
    }

    /**
     * Check-in: petugas tiba di lokasi pasien.
     * Validasi jarak GPS (default toleransi 500m bisa dikonfigurasi).
     */
    public function checkIn(Visit $visit, array $data): Visit
    {
        $toleranceMeters = config('homecare.checkin_tolerance_meters', 500);

        if (isset($data['latitude'], $data['longitude'])) {
            $order    = $visit->order;
            $distance = $this->haversineDistance(
                $data['latitude'], $data['longitude'],
                $order->visit_latitude, $order->visit_longitude
            );

            if ($distance > $toleranceMeters) {
                throw new \Exception(
                    "Lokasi Anda terlalu jauh dari alamat kunjungan ({$distance}m). Maksimum {$toleranceMeters}m."
                );
            }
        }

        $visit->update([
            'status'            => 'arrived',
            'arrived_at'        => now(),
            'checkin_latitude'  => $data['latitude'] ?? null,
            'checkin_longitude' => $data['longitude'] ?? null,
        ]);

        $visit->order->update(['status' => 'in_progress']);

        activity()->performedOn($visit)->causedBy(Auth::user())
            ->withProperties(['lat' => $data['latitude'] ?? null, 'lng' => $data['longitude'] ?? null])
            ->log('Petugas check-in');

        return $visit->fresh();
    }

    /**
     * Mulai tindakan medis (transisi dari arrived → in_progress).
     */
    public function startTreatment(Visit $visit): Visit
    {
        $visit->update([
            'status'     => 'in_progress',
            'started_at' => now(),
        ]);

        activity()->performedOn($visit)->causedBy(Auth::user())->log('Tindakan dimulai');

        return $visit->fresh();
    }

    /**
     * Simpan laporan kunjungan (SOAP + vital signs).
     * Bisa dipanggil berkali-kali (auto-save) sebelum checkout.
     */
    public function saveReport(Visit $visit, array $data): Visit
    {
        $visit->update([
            // SOAP
            'soap_subjective'  => $data['soap_subjective'] ?? $visit->soap_subjective,
            'soap_objective'   => $data['soap_objective'] ?? $visit->soap_objective,
            'soap_assessment'  => $data['soap_assessment'] ?? $visit->soap_assessment,
            'soap_plan'        => $data['soap_plan'] ?? $visit->soap_plan,

            // Vital signs
            'vital_temperature'        => $data['vital_temperature'] ?? $visit->vital_temperature,
            'vital_pulse'              => $data['vital_pulse'] ?? $visit->vital_pulse,
            'vital_respiration'        => $data['vital_respiration'] ?? $visit->vital_respiration,
            'vital_blood_pressure'     => $data['vital_blood_pressure'] ?? $visit->vital_blood_pressure,
            'vital_oxygen_saturation'  => $data['vital_oxygen_saturation'] ?? $visit->vital_oxygen_saturation,
            'vital_weight'             => $data['vital_weight'] ?? $visit->vital_weight,
            'vital_blood_sugar'        => $data['vital_blood_sugar'] ?? $visit->vital_blood_sugar,
            'vital_notes'              => $data['vital_notes'] ?? $visit->vital_notes,

            // Tindakan
            'actions_performed'          => $data['actions_performed'] ?? $visit->actions_performed,
            'medications_given'          => $data['medications_given'] ?? $visit->medications_given,
            'consumables_used'           => $data['consumables_used'] ?? $visit->consumables_used,
            'next_visit_recommendation'  => $data['next_visit_recommendation'] ?? $visit->next_visit_recommendation,
            'notes'                      => $data['notes'] ?? $visit->notes,
        ]);

        return $visit->fresh();
    }

    /**
     * Upload foto dokumentasi kunjungan (bisa multiple).
     */
    public function uploadDocument(Visit $visit, UploadedFile $file, string $type, string $caption = ''): void
    {
        $path = $file->store("visits/{$visit->id}", 'public');

        $visit->visitDocuments()->create([
            'file_path'     => $path,
            'file_name'     => $file->getClientOriginalName(),
            'file_type'     => $file->getMimeType(),
            'document_type' => $type,
            'caption'       => $caption,
            'uploaded_by'   => Auth::id(),
        ]);
    }

    /**
     * Check-out: kunjungan selesai.
     * Validasi laporan SOAP sudah lengkap sebelum checkout.
     */
    public function checkOut(Visit $visit, array $data): Visit
    {
        $this->validateSoapComplete($visit);

        return DB::transaction(function () use ($visit, $data) {
            $visit->update([
                'status'             => 'completed',
                'completed_at'       => now(),
                'checkout_latitude'  => $data['latitude'] ?? null,
                'checkout_longitude' => $data['longitude'] ?? null,
                'patient_signature'  => $data['patient_signature'] ?? null,
                'staff_signature'    => $data['staff_signature'] ?? null,
            ]);

            $visit->order->update(['status' => 'completed']);

            // Notifikasi ke pasien: kunjungan selesai, minta rating
            $this->notificationService->notifyPatient(
                $visit->order->patient,
                'visit.completed',
                $visit->order
            );

            // Notifikasi ke admin & dokter untuk validasi
            $this->notificationService->notifyAdmins('visit.needs_validation', $visit->order);

            activity()->performedOn($visit)->causedBy(Auth::user())
                ->withProperties(['completed_at' => now()->toDateTimeString()])
                ->log('Kunjungan selesai — check-out');

            return $visit->fresh(['order.patient', 'order.service']);
        });
    }

    /**
     * Pasien memberikan rating & komentar.
     */
    public function submitRating(Visit $visit, int $rating, ?string $comment = null): Visit
    {
        $visit->update([
            'rating'         => $rating,
            'rating_comment' => $comment,
            'rated_at'       => now(),
        ]);

        activity()->performedOn($visit)->causedBy(Auth::user())
            ->withProperties(['rating' => $rating])
            ->log('Rating kunjungan diberikan');

        return $visit->fresh();
    }

    /**
     * Dokter/supervisor memvalidasi laporan kunjungan.
     */
    public function validate(Visit $visit, int $doctorId, ?string $notes = null): Visit
    {
        $visit->update([
            'is_validated'    => true,
            'validated_by'    => $doctorId,
            'validated_at'    => now(),
            'validation_notes'=> $notes,
        ]);

        activity()->performedOn($visit)->causedBy(Auth::user())->log('Laporan kunjungan divalidasi dokter');

        return $visit->fresh();
    }

    /**
     * Tandai kunjungan sebagai no-show (pasien tidak ditemukan).
     */
    public function markNoShow(Visit $visit, string $notes): Visit
    {
        return DB::transaction(function () use ($visit, $notes) {
            $visit->update([
                'status' => 'no_show',
                'notes'  => $notes,
            ]);

            $visit->order->update(['status' => 'cancelled']);

            $this->notificationService->notifyAdmins('visit.no_show', $visit->order);

            activity()->performedOn($visit)->causedBy(Auth::user())
                ->withProperties(['notes' => $notes])
                ->log('Kunjungan no-show');

            return $visit->fresh();
        });
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Pastikan SOAP minimal S dan A sudah diisi sebelum checkout.
     */
    private function validateSoapComplete(Visit $visit): void
    {
        if (empty($visit->soap_subjective) || empty($visit->soap_assessment)) {
            throw new \Exception('Laporan SOAP belum lengkap. Minimal isi Subjective dan Assessment sebelum check-out.');
        }
    }

    /**
     * Hitung jarak dua koordinat GPS dalam meter (Haversine formula).
     */
    private function haversineDistance(
        float $lat1, float $lon1,
        ?float $lat2, ?float $lon2
    ): float {
        if (is_null($lat2) || is_null($lon2)) {
            return 0; // Alamat kunjungan tidak ada koordinat → skip validasi
        }

        $earthRadius = 6371000; // meter
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
