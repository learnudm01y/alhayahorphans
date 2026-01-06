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
        Schema::create('orphan_needs', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->timestamps();
        });

        // إضافة القيمة الافتراضية
        \DB::table('orphan_needs')->insert([
            ['description' => 'Unknown', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orphan_needs');
    }
};
