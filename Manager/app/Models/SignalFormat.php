<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SignalFormat extends Model
{
    use HasFactory;

     protected $fillable = [
        'format_name',
        'format_formula',
        'format_demo',
        'short',
        'logo',
        'type',
        'features',
        'group_link',
        'status',
        'group_id'
    ];
}
