<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('professional_profiles', function (Blueprint $table) {
            $table->string('open_time_1')->nullable()->default('08:00');
            $table->string('close_time_1')->nullable()->default('12:00');
            $table->boolean('has_second_range')->default(false);
            $table->string('open_time_2')->nullable()->default('15:30');
            $table->string('close_time_2')->nullable()->default('21:00');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('professional_profiles', function (Blueprint $table) {
            $table->dropColumn(['open_time_1', 'close_time_1', 'has_second_range', 'open_time_2', 'close_time_2']);
        });
    }
};
