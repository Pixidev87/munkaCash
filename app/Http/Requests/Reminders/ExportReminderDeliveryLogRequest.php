<?php

namespace App\Http\Requests\Reminders;

use App\Models\Organization;
use Illuminate\Support\Facades\Gate;

class ExportReminderDeliveryLogRequest extends FilterReminderDeliveriesRequest
{
    private ?Organization $company = null;

    public function company(): Organization
    {
        return $this->company ??= $this->user()->findActiveOrganization((string) $this->route('organization'));
    }

    public function authorize(): bool
    {
        $company = $this->company();
        Gate::authorize('exportData', $company);
        abort_unless($company->exportAvailable(), 410, 'A cég exportálási időszaka lejárt.');

        return true;
    }
}
