<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** ينشئ سجلًا من رد الـ API (يدعم رد send ورد sendvar). */
    public static function fromResponse(array $res, array $attrs): self
    {
        $ok = (bool) ($res['status'] ?? false);
        $pr = data_get($res, 'data.provider_response', data_get($res, 'data', []));

        $cost = $pr['total_cost'] ?? collect($pr['networks_report'] ?? [])->sum('cost');

        return static::create($attrs + [
            'status'     => $ok ? 'sent' : 'failed',
            'code'       => $res['code'] ?? null,
            'error'      => $ok ? null : mb_substr($res['message'] ?? 'Unknown error', 0, 250),
            'accepted'   => $pr['accepted'] ?? null,
            'rejected'   => $pr['rejected'] ?? null,
            'cost'       => $ok ? $cost : null,
            'request_id' => $pr['request_id'] ?? null,
        ]);
    }
}
