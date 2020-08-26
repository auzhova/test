<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdsArea extends Model
{
    protected $guarded = ['id'];
    public $timestamps = false; 
    
    public function tariffAreas()
    {
        return $this->hasOne('App\Models\AdsTariffArea', 'area_id', 'id');
    }
}
