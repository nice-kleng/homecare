<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Specialization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(): Response
    {
        $services = Service::with(['serviceCategory', 'specialization'])
            ->withCount('orders')
            ->orderBy('sort_order')
            ->paginate(20);

        return Inertia::render('Admin/Services/Index', [
            'services'   => ServiceResource::collection($services),
            'categories' => ServiceCategory::where('is_active', true)
                               ->orderBy('sort_order')
                               ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Services/Create', [
            'categories'      => ServiceCategory::where('is_active', true)->get(),
            'specializations' => Specialization::select('id', 'name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'service_category_id'  => ['required', 'exists:service_categories,id'],
            'specialization_id'    => ['nullable', 'exists:specializations,id'],
            'code'                 => ['required', 'string', 'max:20', 'unique:services,code'],
            'name'                 => ['required', 'string', 'max:100'],
            'description'          => ['nullable', 'string'],
            'procedure_notes'      => ['nullable', 'string'],
            'duration_minutes'     => ['required', 'integer', 'min:15'],
            'base_price'           => ['required', 'numeric', 'min:0'],
            'transport_fee'        => ['required', 'numeric', 'min:0'],
            'requires_referral'    => ['boolean'],
            'includes_consumables' => ['boolean'],
            'is_active'            => ['boolean'],
            'sort_order'           => ['integer', 'min:0'],
        ]);

        Service::create($validated);

        return redirect()
            ->route('admin.services.index')
            ->with('success', 'Layanan berhasil ditambahkan.');
    }

    public function edit(Service $service): Response
    {
        return Inertia::render('Admin/Services/Edit', [
            'service'         => new ServiceResource($service->load(['serviceCategory', 'specialization'])),
            'categories'      => ServiceCategory::where('is_active', true)->get(),
            'specializations' => Specialization::select('id', 'name')->get(),
        ]);
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $validated = $request->validate([
            'service_category_id'  => ['required', 'exists:service_categories,id'],
            'specialization_id'    => ['nullable', 'exists:specializations,id'],
            'name'                 => ['required', 'string', 'max:100'],
            'description'          => ['nullable', 'string'],
            'procedure_notes'      => ['nullable', 'string'],
            'duration_minutes'     => ['required', 'integer', 'min:15'],
            'base_price'           => ['required', 'numeric', 'min:0'],
            'transport_fee'        => ['required', 'numeric', 'min:0'],
            'requires_referral'    => ['boolean'],
            'includes_consumables' => ['boolean'],
            'is_active'            => ['boolean'],
            'sort_order'           => ['integer', 'min:0'],
        ]);

        $service->update($validated);

        return redirect()
            ->route('admin.services.index')
            ->with('success', 'Layanan berhasil diperbarui.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        // Soft-delete: nonaktifkan saja, jangan hapus karena ada histori order
        $service->update(['is_active' => false]);

        return back()->with('success', 'Layanan berhasil dinonaktifkan.');
    }
}
