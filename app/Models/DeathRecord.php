<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * سجل الوفيات — داخل قاعدة بيانات السجل المدني (civilregistry)
 */
class DeathRecord extends Model
{
    protected $connection = 'civilregistry';

    protected $table = 'deaths_list';

    protected $fillable = [
        'identity_number',
        'name',
        'status',
        'civil_matched',
        'source_file',
        'imported_by',
    ];

    protected $casts = [
        'civil_matched' => 'boolean',
    ];
}
