<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sms_templates', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('body');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('sms_groups', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->longText('numbers');          // رقم في كل سطر
            $t->timestamps();
        });

        Schema::create('sms_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 10);            // single | bulk | excel
            $t->string('sender', 50);
            $t->string('mobile', 20)->nullable();
            $t->unsignedInteger('recipients')->default(1);
            $t->text('message');               // في Excel يحفظ القالب
            $t->string('status', 10);          // sent | failed
            $t->unsignedInteger('code')->nullable();
            $t->string('error')->nullable();
            $t->unsignedInteger('accepted')->nullable();
            $t->unsignedInteger('rejected')->nullable();
            $t->decimal('cost', 12, 4)->nullable();
            $t->string('request_id', 64)->nullable()->index();
            $t->string('dlr_status', 20)->nullable();
            $t->timestamp('dlr_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
        Schema::dropIfExists('sms_groups');
        Schema::dropIfExists('sms_templates');
    }
};
