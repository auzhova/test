<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdsTariffArea extends Model
{
    protected $guarded = ['id'];
    public $timestamps = false; 
    
    public function areas()
    {
        return $this->hasOne('App\Models\AdsArea', 'id', 'area_id');
    }
}
