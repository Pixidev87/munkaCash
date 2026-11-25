<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * @property int $organization_id
 * @property Carbon $issued_at
 */
#[Fillable(['organization_id', 'email_digest', 'token_digest', 'issued_at', 'expires_at'])]
class OrganizationInvitation extends Model
{
    use MassPrunable;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['issued_at' => 'datetime'];
    }

    public static function digestEmail(string $email): string
    {
        return hash_hmac('sha256', 'organization-invitation:'.Str::lower($email), Config::string('app.key'));
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<=', now());
    }
}
