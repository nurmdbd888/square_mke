<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesRoute extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'routes';

    protected $fillable = ['code', 'name'];
}
