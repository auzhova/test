<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Class AdsPackageAvailable.
 *
 * @method static Builder joinRelationships()
 * @method static Builder available()
 *
 * @package App\Models
 */
class AdsPackageAvailable extends Model {
    protected $guarded = ['id'];
    protected $table = 'ads_package_available';

    /**
     * @param Builder $query
     * @return Builder
     */
    public function scopeJoinRelationships(Builder $query): Builder {
        return $query->select(['ads_package_available.*', 'A1.title as region', 'A2.name as agency'])
            ->leftJoin('ads_regions as A1', 'ads_package_available.region_id', '=', 'A1.id')
            ->leftJoin('agencies as A2', 'ads_package_available.agency_id', '=', 'A2.id');
    }

    public function scopeAvailable($query) {
        /** @var Builder $query */
        return $query->where('available', true);
    }
}
