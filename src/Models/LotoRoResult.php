<?php

namespace LHDev\LotoRo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LotoRoResult extends Model
{
    protected $table = 'lotoro_results';

    protected $fillable = ['draw_id', 'category', 'winners', 'prize', 'report'];

    public $timestamps = false;

    public function draw(): BelongsTo
    {
        return $this->belongsTo(LotoRoDraw::class, 'draw_id');
    }
}
