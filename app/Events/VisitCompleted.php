<?php

namespace App\Events;

use App\Models\Visit;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Difire setelah petugas check-out.
 * Listener: GenerateInvoiceListener, NotifyDoctorForValidationListener
 */
class VisitCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Visit $visit) {}
}
