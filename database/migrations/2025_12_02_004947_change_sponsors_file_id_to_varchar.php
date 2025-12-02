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
        // الخطوة 1: حذف Foreign Key من representative_of_associations
        Schema::table('representative_of_associations', function (Blueprint $table) {
            $table->dropForeign(['re_file_id']);
        });

        // الخطوة 2: تغيير نوع re_file_id في representative_of_associations
        Schema::table('representative_of_associations', function (Blueprint $table) {
            $table->string('re_file_id', 6)->change();
        });

        // الخطوة 3: حذف unique constraint من file_id ثم تغيير النوع
        Schema::table('sponsors', function (Blueprint $table) {
            $table->dropUnique('sponsors_file_id_unique');
        });

        Schema::table('sponsors', function (Blueprint $table) {
            $table->string('file_id', 6)->change();
        });

        Schema::table('sponsors', function (Blueprint $table) {
            $table->unique('file_id');
        });

        // الخطوة 4: إعادة إنشاء Foreign Key
        Schema::table('representative_of_associations', function (Blueprint $table) {
            $table->foreign('re_file_id')->references('file_id')->on('sponsors');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // الخطوة 1: حذف Foreign Key
        Schema::table('representative_of_associations', function (Blueprint $table) {
            $table->dropForeign(['re_file_id']);
        });

        // الخطوة 2: إعادة re_file_id إلى bigint
        Schema::table('representative_of_associations', function (Blueprint $table) {
            $table->unsignedBigInteger('re_file_id')->change();
        });

        // الخطوة 3: حذف unique ثم إعادة file_id إلى bigint
        Schema::table('sponsors', function (Blueprint $table) {
            $table->dropUnique('sponsors_file_id_unique');
        });

        Schema::table('sponsors', function (Blueprint $table) {
            $table->unsignedBigInteger('file_id')->change();
        });

        Schema::table('sponsors', function (Blueprint $table) {
            $table->unique('file_id');
        });

        // الخطوة 4: إعادة إنشاء Foreign Key
        Schema::table('representative_of_associations', function (Blueprint $table) {
            $table->foreign('re_file_id')->references('file_id')->on('sponsors');
        });
    }
};
