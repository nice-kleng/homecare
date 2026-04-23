<?php

namespace App\Repositories;

use App\Models\Patient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PatientRepository
{
    public function __construct(protected Patient $model) {}

    /**
     * Daftar pasien dengan pencarian & pagination (tabel admin).
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->with(['city', 'user'])
            ->when(isset($filters['search']), function ($q) use ($filters) {
                $q->where(function ($sub) use ($filters) {
                    $sub->where('name', 'like', "%{$filters['search']}%")
                        ->orWhere('nik', 'like', "%{$filters['search']}%")
                        ->orWhere('no_rekam_medis', 'like', "%{$filters['search']}%")
                        ->orWhere('phone', 'like', "%{$filters['search']}%");
                });
            })
            ->when(isset($filters['city_id']), fn($q) => $q->where('city_id', $filters['city_id']))
            ->when(isset($filters['insurance_type']), fn($q) =>
                $q->where('insurance_type', $filters['insurance_type'])
            )
            ->where('status', 'active')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Pasien dengan relasi lengkap untuk halaman detail.
     */
    public function findWithRelations(int $id): Patient
    {
        return $this->model
            ->with([
                'user',
                'city',
                'district',
                'village',
                'orders.service',
                'orders.visits.staff.user',
                'medicalRecords.doctor.user',
                'medicalRecords.prescriptions',
            ])
            ->findOrFail($id);
    }

    /**
     * Pasien berdasarkan user_id (untuk portal pasien sendiri).
     */
    public function findByUserId(int $userId): ?Patient
    {
        return $this->model
            ->with(['city', 'user'])
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Generate nomor rekam medis unik.
     */
    public function generateNoRekamMedis(): string
    {
        $prefix = 'RM-' . now()->format('Y');
        $last   = $this->model
            ->where('no_rekam_medis', 'like', "{$prefix}-%")
            ->max('no_rekam_medis');

        $seq = $last ? (int) substr($last, -4) + 1 : 1;

        return $prefix . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Riwayat alergi & penyakit kronis (dibaca petugas sebelum kunjungan).
     */
    public function getMedicalAlerts(int $patientId): array
    {
        $patient = $this->model->findOrFail($patientId);

        return [
            'allergies'          => $patient->allergies,
            'chronic_diseases'   => $patient->chronic_diseases,
            'current_medications'=> $patient->current_medications,
            'medical_notes'      => $patient->medical_notes,
        ];
    }
}
