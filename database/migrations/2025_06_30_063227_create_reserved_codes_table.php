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
        Schema::create('reserved_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 6)->unique(); // الرقم المحجوز
            $table->string('session_id')->nullable(); // معرف الجلسة أو المستخدم
            $table->timestamp('reserved_at')->useCurrent(); // وقت الحجز
            $table->boolean('used')->default(false); // هل تم استخدام الرقم فعلياً
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reserved_codes');
    }
};
