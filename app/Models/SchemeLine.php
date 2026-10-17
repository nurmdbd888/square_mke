<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchemeLine extends Model
{
    use Auditable, SoftDeletes;

    public const KIND_INCOME = 'income';

    public const KIND_COST = 'cost';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    protected $fillable = [
        'doc_date', 'kind', 'label', 'amount', 'status', 'notes', 'posted_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'doc_date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }
}
