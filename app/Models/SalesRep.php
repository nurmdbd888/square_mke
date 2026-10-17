<?php

namespace App\Models;

use App\Domain\Phone\Normalizer;
use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesRep extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['code', 'name', 'phone', 'user_id'];

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = Normalizer::normalise($value);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
