<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdsTariff extends Model
{
    protected $guarded = ['id'];
    public $timestamps = false; 
    
    public function payment()
    {
        return $this->belongsTo('App\Models\Payment', 'tariff_id', 'id');
    }
    
    public function tariffs()
    {
        return $this->belongsTo('App\Models\AdsListingsVising', 'tariff_id', 'id');
    }
    
    public function tariffAreas()
    {
        return $this->hasMany('App\Models\AdsTariffArea', 'tariff_id', 'id');
    }
//    ads_tariff_areas
}
