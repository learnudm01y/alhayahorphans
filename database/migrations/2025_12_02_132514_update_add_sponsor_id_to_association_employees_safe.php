<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // التحقق من وجود العمود sponsor_id
        $hasColumn = Schema::hasColumn('association_employees', 'sponsor_id');

        if (!$hasColumn) {
            // إضافة العمود إذا لم يكن موجوداً
            Schema::table('association_employees', function (Blueprint $table) {
                $table->unsignedBigInteger('sponsor_id')->nullable()->after('id');
            });
        }

        // التحقق من وجود Foreign Key
        $hasForeignKey = $this->hasForeignKey('association_employees', 'association_employees_sponsor_id_foreign');

        if (!$hasForeignKey) {
            // إضافة Foreign Key إذا لم يكن موجوداً
            Schema::table('association_employees', function (Blueprint $table) {
                $table->foreign('sponsor_id')
                      ->references('id')
                      ->on('sponsors')
                      ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('association_employees', function (Blueprint $table) {
            if ($this->hasForeignKey('association_employees', 'association_employees_sponsor_id_foreign')) {
                $table->dropForeign(['sponsor_id']);
            }
        });
    }

    /**
     * Check if foreign key exists
     */
    private function hasForeignKey($table, $foreignKeyName)
    {
        $result = DB::select(
            "SELECT CONSTRAINT_NAME
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = ?
             AND CONSTRAINT_NAME = ?
             AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$table, $foreignKeyName]
        );

        return !empty($result);
    }
};
