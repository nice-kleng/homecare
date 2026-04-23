<?php

namespace App\Services;

use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Prescription;
use App\Repositories\PatientRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MedicalRecordService
{
    public function __construct(
        protected PatientRepository $patientRepo,
    ) {}

    /**
     * Buka episode rekam medis baru untuk pasien.
     * Satu pasien bisa punya multiple episode (tiap kondisi/penyakit terpisah).
     */
    public function openEpisode(Patient $patient, array $data): MedicalRecord
    {
        return DB::transaction(function () use ($patient, $data) {
            $record = MedicalRecord::create([
                'record_number'      => $this->generateRecordNumber(),
                'patient_id'         => $patient->id,
                'order_id'           => $data['order_id'] ?? null,
                'doctor_id'          => $data['doctor_id'],
                'diagnosis_primary'  => $data['diagnosis_primary'],
                'diagnosis_secondary'=> $data['diagnosis_secondary'] ?? null,
                'icd10_code'         => $data['icd10_code'] ?? null,
                'episode_start_date' => $data['episode_start_date'] ?? now()->toDateString(),
                'treatment_plan'     => $data['treatment_plan'] ?? null,
                'doctor_instructions'=> $data['doctor_instructions'] ?? null,
                'diet_instruction'   => $data['diet_instruction'] ?? null,
                'activity_restriction'=> $data['activity_restriction'] ?? null,
                'status'             => 'active',
            ]);

            // Tambahkan resep awal jika ada
            if (!empty($data['prescriptions'])) {
                foreach ($data['prescriptions'] as $rx) {
                    $this->addPrescription($record, $rx);
                }
            }

            activity()->performedOn($record)->causedBy(Auth::user())
                ->withProperties(['record_number' => $record->record_number])
                ->log('Episode rekam medis dibuka');

            return $record->load(['patient', 'prescriptions']);
        });
    }

    /**
     * Update episode (dokter merevisi diagnosis / rencana pengobatan).
     */
    public function updateEpisode(MedicalRecord $record, array $data): MedicalRecord
    {
        $record->update(array_filter([
            'diagnosis_primary'   => $data['diagnosis_primary'] ?? null,
            'diagnosis_secondary' => $data['diagnosis_secondary'] ?? null,
            'icd10_code'          => $data['icd10_code'] ?? null,
            'treatment_plan'      => $data['treatment_plan'] ?? null,
            'doctor_instructions' => $data['doctor_instructions'] ?? null,
            'diet_instruction'    => $data['diet_instruction'] ?? null,
            'activity_restriction'=> $data['activity_restriction'] ?? null,
        ], fn($v) => !is_null($v)));

        activity()->performedOn($record)->causedBy(Auth::user())->log('Episode rekam medis diperbarui');

        return $record->fresh(['prescriptions']);
    }

    /**
     * Tutup episode (perawatan selesai / pasien dirujuk).
     */
    public function closeEpisode(MedicalRecord $record, string $reason, string $status = 'closed'): MedicalRecord
    {
        $record->update([
            'status'           => $status, // 'closed' atau 'referred'
            'episode_end_date' => now()->toDateString(),
            'closure_notes'    => $reason,
        ]);

        // Non-aktifkan semua resep yang masih aktif
        $record->prescriptions()->where('is_active', true)->update(['is_active' => false]);

        activity()->performedOn($record)->causedBy(Auth::user())
            ->withProperties(['status' => $status, 'reason' => $reason])
            ->log('Episode rekam medis ditutup');

        return $record->fresh();
    }

    /**
     * Tambah catatan perkembangan pasien (progress note) per kunjungan.
     */
    public function addProgressNote(MedicalRecord $record, array $data): void
    {
        $record->progressNotes()->create([
            'visit_id'        => $data['visit_id'] ?? null,
            'staff_id'        => $data['staff_id'],
            'progress_note'   => $data['progress_note'],
            'condition_trend' => $data['condition_trend'] ?? 'stable',
            'noted_at'        => $data['noted_at'] ?? now(),
        ]);
    }

    /**
     * Tambah / update resep obat.
     */
    public function addPrescription(MedicalRecord $record, array $data): Prescription
    {
        return $record->prescriptions()->create([
            'prescribed_by'  => $data['prescribed_by'] ?? Auth::id(),
            'drug_name'      => $data['drug_name'],
            'dosage'         => $data['dosage'],
            'frequency'      => $data['frequency'],
            'route'          => $data['route'] ?? null,
            'duration_days'  => $data['duration_days'] ?? null,
            'instructions'   => $data['instructions'] ?? null,
            'start_date'     => $data['start_date'] ?? now()->toDateString(),
            'end_date'       => $data['end_date'] ?? null,
            'is_active'      => true,
        ]);
    }

    /**
     * Stop / hentikan resep (obat diganti / efek samping).
     */
    public function stopPrescription(Prescription $prescription, string $reason): void
    {
        $prescription->update([
            'is_active' => false,
            'end_date'  => now()->toDateString(),
            'instructions' => ($prescription->instructions ?? '') . " [Dihentikan: {$reason}]",
        ]);
    }

    /**
     * Ambil rekam medis aktif pasien (episode yang masih berjalan).
     */
    public function getActiveEpisode(Patient $patient): ?MedicalRecord
    {
        return $patient->medicalRecords()
            ->where('status', 'active')
            ->with(['prescriptions' => fn($q) => $q->where('is_active', true)])
            ->latest()
            ->first();
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function generateRecordNumber(): string
    {
        $prefix = 'REC-' . now()->format('Ymd');
        $last   = MedicalRecord::where('record_number', 'like', "{$prefix}%")->max('record_number');
        $seq    = $last ? (int) substr($last, -4) + 1 : 1;
        return $prefix . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
