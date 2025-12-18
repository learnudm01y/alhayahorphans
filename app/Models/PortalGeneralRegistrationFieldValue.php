<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalGeneralRegistrationFieldValue extends Model
{
    protected $table = 'portal_general_registration_field_values';

    protected $fillable = [
        'sponsorship_id',
        'file_id_number',
        'identity_number',
        'field_key',
        'field_value',
        'updated_by_user_id',
    ];
}
