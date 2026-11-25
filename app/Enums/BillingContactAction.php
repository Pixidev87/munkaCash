<?php

namespace App\Enums;

enum BillingContactAction: string
{
    case AddressChanged = 'address_changed';
    case Confirmed = 'confirmed';
    case ConfirmationRevoked = 'confirmation_revoked';
}
