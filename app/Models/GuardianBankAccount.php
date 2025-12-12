<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuardianBankAccount extends Model
{
    use HasFactory;
    protected $fillable = [
        'guardian_registration',
        'bank_name',
        'iban_usd',
        're_id_number',
        're_guardian_name',
        're_phone_number',
        'person_owner_identity_number',
        'iban_shekel',
        'check_account',
    ];

    protected $casts = [
        'check_account' => 'integer',
    ];

    public function guardian()
    {
        return $this->belongsTo(Data::class, 'guardian_registration', 'registration_id');
    }

    public function bank()
    {
        return $this->belongsTo(BankName::class, 'bank_name');
    }
}
