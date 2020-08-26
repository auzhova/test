<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdsListingsVising extends Model
{
    protected $guarded = ['id'];
    //
    public function agent()
    {
        return $this->hasOne('App\Models\Agent', 'id', 'agent_id');
    }

    public function verifier()
    {
        return $this->hasOne('App\Models\Agent', 'id', 'verifier_id');
    }

    public function listing()
    {
        return $this->hasOne('App\Models\Listing', 'id', 'listing_id');
    }
    
    public function tariffs()
    {
        return $this->hasOne('App\Models\AdsTariff', 'id', 'tariff_id');
    }
    
    public function mainphoto()
    {
        return $this->hasOne('App\Models\Photo', 'imageable_id')->where('is_main', 1);
    }
}
