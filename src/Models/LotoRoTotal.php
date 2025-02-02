<?php

namespace LHDev\LotoRo\Models;

use App\Traits\ModelCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LotoRoTotal extends Model
{
    protected $table = 'lotoro_totals';

    protected $fillable = ['draw_id', 'total_prize'];

    public $timestamps = false;

    public function draw(): BelongsTo
    {
        return $this->belongsTo(LotoRoDraw::class, 'draw_id');
    }
}
