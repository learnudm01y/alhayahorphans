<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuardianBankAccount extends Model
{
    use HasFactory;
    protected $fillable = [
        'guardian_registration','bank_name','account_number_or_related_phone_number'
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
