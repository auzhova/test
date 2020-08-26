<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use App\Jobs\AdsPrices;

/**
 * Class AdsSitePrice.
 *
 * @method static Builder joinRelations()
 *
 * @package App\Models
 */
class AdsSitePrice extends BaseModel {
    
    protected $guarded = ['id'];
    protected $table = 'ads_site_prices';
    protected $casts = [
        'is_new_building' => 'bool'
    ];

    public function afterSave($record){
        Log::info('afterSave AdsSitePrice');
//        Log::info($record->toArray());
        dispatch((new AdsPrices())->onQueue('ads'));
    }
    /**
     * @param Builder $query
     * @return Builder
     */
    public function scopeJoinRelations(Builder $query): Builder {
        return $query->select([
            'ads_site_prices.id',
            'ads_site_prices.region_id',
            'A3.title as region',
            'ads_site_prices.is_premium',
            'ads_site_prices.is_new_building',
            'ads_site_prices.amount',
            'ads_site_prices.status',
            'ads_site_prices.created_at',
            'ads_site_prices.deal_type as deal_type_id',
            'ads_site_prices.realty_type as realty_type_id',
            'A1.value as deal_type',
            'A2.value as realty_type'
        ])
            ->leftJoin('ref_books_values as A1', 'ads_site_prices.deal_type', '=', 'A1.id')
            ->leftJoin('ref_books_values as A2', 'ads_site_prices.realty_type', '=', 'A2.id')
            ->leftJoin('ads_regions as A3', 'ads_site_prices.region_id', '=', 'A3.id');
    }
}
