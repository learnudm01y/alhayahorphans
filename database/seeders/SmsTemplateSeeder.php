<?php

namespace Database\Seeders;

use App\Models\SmsTemplate;
use Illuminate\Database\Seeder;

class SmsTemplateSeeder extends Seeder
{
    /** قوالب أولية (آمن إعادة تشغيلها). */
    public function run(): void
    {
        $templates = [
            ['title' => 'تذكير بالموعد',  'body' => 'عزيزنا المتدرب، نذكّرك بموعدك يوم {date} الساعة {time}.'],
            ['title' => 'نتيجة طلب',      'body' => 'عزيزي {name}، تم استلام طلبك رقم {ref} وسيتم التواصل معك قريبًا.'],
            ['title' => 'إشعار عام',      'body' => '{name}، {body}'],
        ];

        foreach ($templates as $t) {
            SmsTemplate::firstOrCreate(['title' => $t['title']], $t + ['is_active' => true]);
        }
    }
}
