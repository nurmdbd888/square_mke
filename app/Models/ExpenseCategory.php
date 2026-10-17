<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseCategory extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['key', 'label', 'sort'];

    protected function casts(): array
    {
        return ['sort' => 'integer'];
    }
}
