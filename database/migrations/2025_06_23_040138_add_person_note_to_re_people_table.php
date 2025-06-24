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
        Schema::table('re_people', function (Blueprint $table) {
            $table->text('person_note')->nullable()->after('person_type_of_guarantee')->comment('Note about the person');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('re_people', function (Blueprint $table) {
            //
        });
    }
};
