<?php

namespace App\Enums;

enum OrganizationAccessAction: string
{
    case InvitationIssued = 'invitation_issued';
    case InvitationAccepted = 'invitation_accepted';
    case InvitationCancelled = 'invitation_cancelled';
    case MemberRevoked = 'member_revoked';
}
