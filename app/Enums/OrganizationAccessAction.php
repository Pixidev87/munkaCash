<?php

namespace App\Enums;

enum OrganizationAccessAction: string
{
    case InvitationIssued = 'invitation_issued';
    case InvitationAccepted = 'invitation_accepted';
    case InvitationCancelled = 'invitation_cancelled';
    case CustomerExport = 'customers_export';
    case InvoiceExport = 'invoices_export';
    case FilteredInvoiceExport = 'filtered_invoices_export';
    case ReminderDeliveryExport = 'reminder_delivery_export';
    case MemberRevoked = 'member_revoked';
}
