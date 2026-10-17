<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportMappingProfile extends Model
{
    protected $fillable = ['entity', 'name', 'mapping', 'created_by'];

    protected function casts(): array
    {
        return ['mapping' => 'array'];
    }

    /**
     * @return HasMany<EntityImportBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(EntityImportBatch::class, 'mapping_profile_id');
    }
}
