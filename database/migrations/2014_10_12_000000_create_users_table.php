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
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('avatar')->default('/uploads/avatar.jpg');
            $table->string('email')->unique();
            $table->enum('role',['admin','user'])->default('user');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->bigInteger('phone')->default('+970598888888');
            $table->bigInteger('alt_phone')->default('+970598888888');

            // استبدال country و flag بـ country_code
            $table->string('country_code')->default('PS'); // مثال: PS

            $table->rememberToken();
            $table->timestamps();

            // إضافة العلاقة Foreign Key
            $table->foreign('country_code')
                ->references('code')
                ->on('ci_birth_cd')
                ->onUpdate('cascade');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
