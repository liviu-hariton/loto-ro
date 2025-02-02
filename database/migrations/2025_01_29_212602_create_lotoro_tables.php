<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lotoro_draws', function (Blueprint $table) {
            $table->id();

            $table->string('draw_type');
            $table->date('draw_date')->index();
            $table->string('numbers'); // Store as comma-separated values

            $table->timestamps();
        });

        Schema::create('lotoro_results', function (Blueprint $table) {
            $table->id();

            $table->foreignId('draw_id')->constrained('lotoro_draws')->onDelete('cascade');
            $table->string('category');
            $table->string('winners');
            $table->decimal('prize', 15)->nullable();
            $table->decimal('report', 15)->nullable();
        });

        Schema::create('lotoro_totals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('draw_id')->constrained('lotoro_draws')->onDelete('cascade');
            $table->decimal('total_prize', 15);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lotoro_totals');
        Schema::dropIfExists('lotoro_results');
        Schema::dropIfExists('lotoro_draws');
    }
};
