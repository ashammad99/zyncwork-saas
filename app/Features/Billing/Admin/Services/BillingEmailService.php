<?php

namespace App\Features\Billing\Admin\Services;

use App\Core\BaseService;
use App\Features\ActivityLog\Events\ActivityLogged;
use App\Features\Auth\Models\User;
use App\Features\Tenancy\Models\Tenant;
use App\Mail\GeneralMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Changes where a tenant's billing emails go. The new address isn't verified
 * (a decision for now); the old one is told, so a change nobody asked for
 * gets noticed.
 */
class BillingEmailService extends BaseService
{
    /** @return bool whether anything changed */
    public function update(Tenant $tenant, string $email, User $by): bool
    {
        $old = $tenant->billing_email;
        $new = Str::lower(trim($email));

        if ($old !== null && Str::lower($old) === $new) {
            return false;
        }

        $tenant->forceFill(['billing_email' => $new])->save();

        event(new ActivityLogged($by, 'billing_email_changed', 'Changed the billing email from '.($old ?? 'none')." to {$new}.", 'security'));

        if ($old !== null) {
            Mail::to($old)->send(new GeneralMail(
                mailSubject: __('billing.email_changed.subject', ['app' => config('app.name')]),
                contentView: 'emails.billing.billing-email-changed',
                data: [
                    'companyName' => $tenant->name,
                    'changedBy' => $by->name,
                    'newEmail' => $new,
                ]
            ));
        }

        return true;
    }
}
