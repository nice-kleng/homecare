<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PatientResource;
use App\Models\City;
use App\Models\Patient;
use App\Repositories\PatientRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PatientController extends Controller
{
    public function __construct(
        protected PatientRepository $patientRepo,
    ) {}

    public function index(Request $request): Response
    {
        $filters  = $request->only(['search', 'city_id', 'insurance_type']);
        $patients = $this->patientRepo->paginate($filters, 20);

        return Inertia::render('Admin/Patients/Index', [
            'patients' => PatientResource::collection($patients),
            'filters'  => $filters,
            'cities'   => City::orderBy('name')->select('id', 'name')->get(),
        ]);
    }

    public function show(Patient $patient): Response
    {
        $patient = $this->patientRepo->findWithRelations($patient->id);

        return Inertia::render('Admin/Patients/Show', [
            'patient'      => new PatientResource($patient),
            'medicalAlerts'=> $this->patientRepo->getMedicalAlerts($patient->id),
        ]);
    }

    public function edit(Patient $patient): Response
    {
        return Inertia::render('Admin/Patients/Edit', [
            'patient' => new PatientResource(
                $this->patientRepo->findWithRelations($patient->id)
            ),
            'cities'  => City::orderBy('name')->select('id', 'name')->get(),
        ]);
    }

    public function update(Request $request, Patient $patient): RedirectResponse
    {
        $validated = $request->validate([
            'name'                     => ['required', 'string', 'max:100'],
            'nik'                      => ['nullable', 'string', 'size:16', "unique:patients,nik,{$patient->id}"],
            'gender'                   => ['required', 'in:laki-laki,perempuan'],
            'birth_date'               => ['required', 'date', 'before:today'],
            'birth_place'              => ['nullable', 'string', 'max:100'],
            'blood_type'               => ['nullable', 'in:A,B,AB,O,unknown'],
            'phone'                    => ['nullable', 'string', 'max:20'],
            'address'                  => ['required', 'string'],
            'rt'                       => ['nullable', 'string', 'max:5'],
            'rw'                       => ['nullable', 'string', 'max:5'],
            'city_id'                  => ['nullable', 'exists:cities,id'],
            'district_id'              => ['nullable', 'exists:districts,id'],
            'village_id'               => ['nullable', 'exists:villages,id'],
            'postal_code'              => ['nullable', 'string', 'max:10'],
            'insurance_type'           => ['required', 'in:umum,bpjs,asuransi_swasta,perusahaan'],
            'insurance_number'         => ['nullable', 'string', 'max:50'],
            'emergency_contact_name'   => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone'  => ['nullable', 'string', 'max:20'],
            'emergency_contact_relation'=> ['nullable', 'string', 'max:50'],
            'allergies'                => ['nullable', 'string'],
            'chronic_diseases'         => ['nullable', 'string'],
            'current_medications'      => ['nullable', 'string'],
            'medical_notes'            => ['nullable', 'string'],
        ]);

        $patient->update($validated);

        return redirect()
            ->route('admin.patients.show', $patient)
            ->with('success', 'Data pasien berhasil diperbarui.');
    }

    /**
     * Non-aktifkan pasien (soft approach, tidak hapus data medis).
     */
    public function deactivate(Patient $patient): RedirectResponse
    {
        $patient->update(['status' => 'inactive']);

        return back()->with('success', 'Pasien berhasil dinonaktifkan.');
    }

    public function activate(Patient $patient): RedirectResponse
    {
        $patient->update(['status' => 'active']);

        return back()->with('success', 'Pasien berhasil diaktifkan.');
    }
}
