<?php

namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Log;
use App\Jobs\AdsPrices;

/**
 * Class AdsPackage.
 *
 * @property array $settings
 *
 * @method static Builder joinRelations()
 *
 * @package App\Models
 */
class AdsPackage extends BaseModel {

    use SoftDeletes;

    public $casts = [
        'settings' => 'array'
    ];

    public const SECTION_REGIONAL = 'regional';
    public const SECTION_TOP = 'top';
    public const SECTION_FREE = 'free';
    public const SECTION_STANDART = 'standart';
    public const SECTION_ADDITIONAL = 'additional';
    public const SECTION_OTHER = 'other';

    public const SECTIONS_MAP = [
        self::SECTION_TOP        => 'Топовый тариф',
        self::SECTION_REGIONAL   => 'Региональный тариф',
        self::SECTION_FREE       => 'Бесплатный тариф',
        self::SECTION_STANDART   => 'Базовый тариф',
        self::SECTION_ADDITIONAL => 'Расширенный тариф',
    ];

    protected $guarded = ['id'];
    protected $table = 'ads_packages';
    protected $dates = ['deleted_at'];

    public function afterSave($record) {
        Log::info('afterSave AdsPackage');
//        Log::info($record->toArray());
        dispatch((new AdsPrices())->onQueue('ads'));
    }

    public function site() {
        return $this->hasMany(AdsPackageSite::class, 'package_id', 'id');
    }

    public function siteDirect() {
        return $this->belongsToMany(AdsSite::class, 'ads_package_sites', 'package_id', 'site_id', 'id')
            ->withPivot('is_premium');
    }

    public function available() {
        return $this->hasMany(AdsPackageAvailable::class, 'package_id', 'id');
    }

    private function getMockedSections(): string {
        return 'SELECT "' . static::SECTION_STANDART . '" AS "section", "Базовый" AS title UNION ALL 
            SELECT "' . static::SECTION_TOP . '" AS "section", "Топовый" AS title UNION ALL
            SELECT "' . static::SECTION_ADDITIONAL . '" AS "section", "Расширенный" AS title UNION ALL
            SELECT "' . static::SECTION_FREE . '" AS "section", "Бесплатный" AS title UNION ALL
            SELECT "' . static::SECTION_REGIONAL . '" AS "section", "Региональный" AS title';
    }

    /**
     * @param Builder $query
     * @return Builder
     */
    public function scopeJoinRelations(Builder $query): Builder {
        return $query->select(['ads_packages.*', DB::raw('COUNT(A1.id) as platforms_count'), 'SECTIONS.title as section_title'])->leftJoin('ads_package_sites as A1', 'ads_packages.id',
            '=', 'A1.package_id')
            ->join(DB::raw('(' . $this->getMockedSections() . ') as SECTIONS'), 'ads_packages.section', '=', 'SECTIONS.section')
            ->groupBy('ads_packages.id');
    }

    public function scopePremium(Builder $query): Builder {
        return $query->select(['ads_packages.*', DB::raw('SUM(ads_package_sites.is_premium) as premium')])
            ->leftJoin('ads_package_sites', 'ads_packages.id', '=', 'ads_package_sites.package_id', '=')
            ->groupBy('ads_packages.id');
    }

    /**
     * Отфильтровывает площадки внутри пакета согласно его премиальности
     * @param bool $premiality
     * @return SupportCollection
     */
    public function filterPackagePlatfroms(bool $premiality): SupportCollection {
        $filteredPackages = [];

        foreach ($this->siteDirect as $platform) {
            /** @var AdsSite $platform */
            if ($platform->hasPremiumPrice($premiality)) {
                $filteredPackages[] = $platform;
            }
        }

        return collect($filteredPackages);
    }
}
