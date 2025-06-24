<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;
    protected $fillable = [
        'item_name',
        'item_processed_name',
        'item_en_name',
        'item_description',
        'item_short_description',
        'item_en_short_description',
        'item_en_description',
        'price',
        'parent_id',
    ];
}
