<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('document_types', function (Blueprint $table) {
            if (Schema::hasColumn('document_types', 'is_required')) {
                $table->dropColumn('is_required');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('document_types', function (Blueprint $table) {
            if (!Schema::hasColumn('document_types', 'is_required')) {
                $table->boolean('is_required')->default(0)->comment('0=اختياري, 1=إجباري');
            }
        });
    }
};
