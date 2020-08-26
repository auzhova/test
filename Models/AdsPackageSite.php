<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdsPackageSite extends Model
{
    protected $guarded = ['id'];
    public $timestamps = false; 
    
    public function site_price(){
        return $this->hasOne(AdsSitePrice::class, 'site_id', 'id');
    }
    
    public function site(){
        return $this->hasOne(AdsSite::class, 'site_id', 'id');
    }

}
