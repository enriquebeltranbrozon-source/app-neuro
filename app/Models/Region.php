<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Region extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (Region $region) {
            if (empty($region->slug)) {
                $region->slug = Str::slug($region->name);
            }
        });
    }

    public function clinics(): HasMany
    {
        return $this->hasMany(Clinic::class);
    }
}