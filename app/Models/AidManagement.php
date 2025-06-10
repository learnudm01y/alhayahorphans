<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AidManagement extends Model
{
    use HasFactory;
    protected $primaryKey = 'file_id';
    public $incrementing = false;
    protected $fillable = [
        'first_name','second_name','third_name','last_name',
        'aid_id_number','aid_category','aid_phone_number','aid_quantity',
        'aid_delivery_status','aid_family_individuals_number'
    ];
}
