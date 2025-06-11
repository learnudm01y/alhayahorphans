<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankName extends Model
{
    use HasFactory;
    protected $fillable = ['description'];
    protected $table = 'bank_names';

    public function guardianAccounts()
    {
        return $this->hasMany(GuardianBankAccount::class, 'bank_name');
    }
}
