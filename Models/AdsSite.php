<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdsSite extends Model
{
    protected $guarded = ['id'];
    
    public function price(){
        return $this->hasMany(AdsSitePrice::class, 'site_id', 'id');
    }

    /**
     * Есть ли премиальная цена у площадки?
     * @param bool $premiality
     * @return bool
     */
    public function hasPremiumPrice(bool $premiality): bool {
        foreach ($this->price as $price) {
            if ($premiality === (bool)$price->is_premium) {
                return true;
            }
        }

        return false;
    }
}
