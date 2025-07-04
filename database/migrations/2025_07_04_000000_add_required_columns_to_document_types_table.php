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
            if (!Schema::hasColumn('document_types', 'basic_required')) {
                $table->boolean('basic_required')->default(0)->comment('0=اختياري, 1=إجباري');
            }
            if (!Schema::hasColumn('document_types', 'deceased_required')) {
                $table->boolean('deceased_required')->default(0)->comment('0=اختياري, 1=إجباري');
            }
            if (!Schema::hasColumn('document_types', 'family_required')) {
                $table->boolean('family_required')->default(0)->comment('0=اختياري, 1=إجباري');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('document_types', function (Blueprint $table) {
            if (Schema::hasColumn('document_types', 'basic_required')) {
                $table->dropColumn('basic_required');
            }
            if (Schema::hasColumn('document_types', 'deceased_required')) {
                $table->dropColumn('deceased_required');
            }
            if (Schema::hasColumn('document_types', 'family_required')) {
                $table->dropColumn('family_required');
            }
        });
    }
};
