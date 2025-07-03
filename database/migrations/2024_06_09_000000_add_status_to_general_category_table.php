<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusToGeneralCategoryTable extends Migration
{
    public function up()
    {
        Schema::table('general_category', function (Blueprint $table) {
            $table->boolean('status')->default(true)->after('id')->comment('حالة عمل القسم: 1=يعمل, 0=لا يعمل');
        });
    }

    public function down()
    {
        Schema::table('general_category', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
}
