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
        Schema::table('sponsors', function (Blueprint $table) {
            // التحقق من عدم وجود الأعمدة قبل إضافتها
            if (!Schema::hasColumn('sponsors', 'contact_person')) {
                $table->string('contact_person')->nullable()->after('sponsor_name');
            }
            if (!Schema::hasColumn('sponsors', 'phone')) {
                $table->string('phone', 50)->nullable()->after('contact_person');
            }
            if (!Schema::hasColumn('sponsors', 'email')) {
                $table->string('email')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('sponsors', 'address')) {
                $table->string('address', 500)->nullable()->after('email');
            }
            if (!Schema::hasColumn('sponsors', 'website')) {
                $table->string('website')->nullable()->after('address');
            }
            if (!Schema::hasColumn('sponsors', 'description')) {
                $table->text('description')->nullable()->after('website');
            }
            if (!Schema::hasColumn('sponsors', 'status')) {
                $table->boolean('status')->default(1)->after('description');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsors', function (Blueprint $table) {
            $table->dropColumn([
                'contact_person',
                'phone',
                'email',
                'address',
                'website',
                'description',
                'status'
            ]);
        });
    }
};
