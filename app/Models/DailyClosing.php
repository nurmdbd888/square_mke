<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyClosing extends Model
{
    use Auditable;

    protected $fillable = [
        'closed_on', 'cash_opening', 'cash_in', 'cash_out', 'cash_closing',
        'bank_opening', 'bank_in', 'bank_out', 'bank_closing', 'notes', 'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'closed_on' => 'date:Y-m-d',
            'cash_opening' => 'decimal:2',
            'cash_in' => 'decimal:2',
            'cash_out' => 'decimal:2',
            'cash_closing' => 'decimal:2',
            'bank_opening' => 'decimal:2',
            'bank_in' => 'decimal:2',
            'bank_out' => 'decimal:2',
            'bank_closing' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
