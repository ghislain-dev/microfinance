<?php

declare(strict_types=1);

namespace App\Domain\Organisation\Models;

use App\Models\User;
use App\Support\MultiOrganisation\AppartientAOrganisation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $expire_le
 * @property Carbon|null $utilise_le
 */
#[Fillable(['code_hache', 'canal', 'expire_le'])]
#[Table('codes_otp')]
class CodeOtp extends Model
{
    use AppartientAOrganisation;

    /**
     * @return BelongsTo<User, $this>
     */
    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expire_le' => 'datetime',
            'utilise_le' => 'datetime',
            'tentatives' => 'integer',
        ];
    }
}
