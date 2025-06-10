<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CiBirthSeeder extends Seeder
{
    public function run()
    {
        DB::table('ci_birth_tb_cd')->insert([
            ['id' => 1, 'CI_BIRTH_TB_CD' => 'أقاليم'],
            ['id' => 2, 'CI_BIRTH_TB_CD' => 'دول'],
            ['id' => 3, 'CI_BIRTH_TB_CD' => 'مدن - أراضي فلسطينية'],
            ['id' => 4, 'CI_BIRTH_TB_CD' => 'مناطق 48'],
        ]);
    }
}
