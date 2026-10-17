<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;

class CustomField extends Model
{
    use Auditable;

    public const ENTITIES = ['product', 'retailer'];

    protected $fillable = ['entity', 'key', 'label', 'type', 'sort'];
}
