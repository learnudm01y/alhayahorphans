<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            $table->json('save_local_attachments')->nullable()->comment('قائمة بمعرفات أنواع الوثائق المفعّل فيها الحفظ المحلي')->change();
        });
    }

    public function down(): void
    {
        Schema::table('sponsor_field_settings', function (Blueprint $table) {
            $table->boolean('save_local_attachments')->default(0)->change();
        });
    }
};
