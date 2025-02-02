<?php

namespace LHDev\LotoRo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LotoRoDraw extends Model
{
    protected $table = 'lotoro_draws';

    protected $fillable = ['draw_type', 'draw_date', 'numbers'];

    public function results(): HasMany
    {
        return $this->hasMany(LotoRoResult::class, 'draw_id');
    }

    public function total(): HasOne
    {
        return $this->hasOne(LotoRoTotal::class, 'draw_id');
    }

    public function getNumbersArrayAttribute(): array
    {
        return explode(',', $this->numbers);
    }
}
