<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** إعدادات الرسائل النصية (مفاتيح/قيم) — بنفس نمط WhatsAppSetting */
class SmsSetting extends Model
{
    protected $table = 'sms_settings';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        $row = static::query()->where('key', $key)->first(['value']);

        return $row !== null ? $row->value : $default;
    }

    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function enabled(string $key, bool $default = true): bool
    {
        $value = static::get($key);

        if ($value === null) {
            return $default;
        }

        return $value === '1';
    }

    /**
     * المرسل الافتراضي: إعداد الشاشة إن وُجد، وإلا القيمة في .env (NSMS_SENDER).
     */
    public static function defaultSender(): string
    {
        return (string) (static::get('default_sender') ?: config('services.nsms.sender'));
    }
}
