<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Claim extends Model
{
    use Auditable, SoftDeletes;

    public const STATUS_OPEN = 'open';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_SUBMITTED,
        self::STATUS_APPROVED,
        self::STATUS_SETTLED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'number', 'doc_date', 'status', 'supplier_id', 'amount', 'description', 'notes',
        'submitted_at', 'settled_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'doc_date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
