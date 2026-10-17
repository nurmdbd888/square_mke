<?php

namespace App\Models;

use App\Domain\Phone\Normalizer;
use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['code', 'name', 'bin', 'address', 'phone'];

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = Normalizer::normalise($value);
    }
}
