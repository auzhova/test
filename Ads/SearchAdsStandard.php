<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\RefBooksValue;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Models\AdsOption;
use Illuminate\Support\Facades\Redis;

class SearchAdsStandard implements SearchAdsInterface
{

    const LIMIT = 10;
    const OFFSET = 0;

    const MAPPING = [
        //Категория
        'deal_type' => [
            7 => 4, //Продажа
            6 => 5 //Аредна
        ],

        'deal_type_name' =>[
            4 => 'Продажа',
            5 => 'Аренда'
        ],
        //Тип сделки
        'realty_type' =>[
            3 => 1, //Жилая
            4 => 2, //Нежилая
            5 => 3 //Земля
        ],
        'realty_type_name' =>[
            1 => 'Жилая',
            2 => 'Нежилая',
            3 => 'Земля'
        ],
        //Тип недвижимости (квартира, апартаменты, гараж и т.д)
        'realty_subtype' =>[
            15 => 8, 16 => 9, 17 => 10, 18 => 11, 19 => 12, 20 => 13, 21 => 14, 22 => 15, 23 => 16, 24 => 17, 25 => 18, 26 => 19, 27 => 20, 28 => 21, 29 => 22, 30 => 23, 31 => 24, 32 => 25, 33 => 26, 34 => 27, 36 => 28, 37 => 29, 38 => 30, 39 => 38, 40 => 39, 41 => 42, 42 => 31, 43 => 32, 44 => 33, 45 => 34, 46 => 35,
            84 => 83, 85 => 84, 86 => 85, 88 => 86, 90 => 87, 91 => 88, 92 => 89, 93 => 90, 94 => 91, 95 => 92, 96 => 93, 97 => 94, 98 => 95, 99 => 96, 100 => 97, 101 => 98, 102 => 99, 103 => 100, 104 => 101, 105 => 102, 106 => 103, 107 => 104, 108 => 105, 109 => 106, 110 => 107, 111 => 108, 112 => 109, 113 => 110, 114 => 111, 115 => 112, 116 => 113, 117 => 114, 118 => 115, 119 => 116, 120 => 117, 121 => 118, 122 => 119, 123 => 120, 124 => 121, 125 => 122, 126 => 123, 127 => 124, 129 => 125, 130 => 126, 131 => 127, 132 => 128, 133 => 129, 134 => 130, 135 => 131, 136 => 132, 137 => 133, 138 => 134, 139 => 135, 140 => 136, 141 => 137, 142 => 138, 143 => 139, 144 => 140, 145 => 141, 146 => 142, 147 => 143, 148 => 144, 150 => 145
        ]
    ];
    CONST MULTIPLE = [
        1 => 'metro',
        2 => 'district'
    ];
    protected $oListingOptions;
    protected $multiple;

    public function __construct() {
        $this->url = env('PAGE_URL', url('/'));
        $oOptions = Cache::remember('SearchListingsOptions', 10000, function() {
            return AdsOption::whereStatus(1)->get()->keyBy('id');
        });
        $this->oListingOptions = $oOptions;
        $this->multiple = self::MULTIPLE;
    }

    /**
     * Фильтрация данных
     * @param $filters
     * @param  $bounds
     * @return object
     */
    public function filter($filters, $bounds){
        $query = DB::table('ads')->select('*')
            ->addSelect('ads.id as id') ->addSelect('ads_prices.value as value')->where('ads.status', 1)
            ->leftJoin('ads_prices', 'ads.id', '=', 'ads_prices.ads_id')
            ->leftJoin('ads_contacts', 'ads_contacts.id', '=', 'ads.contact_id')
            ->leftJoin('ads_params', 'ads.id', '=', 'ads_params.ads_id');

        $refBooksValue = RefBooksValue::where('status', 1)->where('sysname', '!=', '');
        switch ($filters['is_freshness']) {
            case '1' :
                {
                    $query->where('published_at', '>', Carbon::today()->subDays(7)->toDateTimeString());
                    break;
                }
            case '0' :
                {
                    $query->where('published_at', '>', Carbon::today()->subMonth(2)->toDateTimeString());
                    break;
                }
            default :
                {
                    $query->where('published_at', '>', Carbon::today()->toDateTimeString());
                    break;
                }
        }

        switch ($filters['searchFilters']) {
            case '1' :
                {
                    $query->where('ads_contacts.person_type', '<>', 2);
                    break;
                }
            case '0' :
                {
                    $query->where('ads_contacts.person_type', '=', 2);
                    break;
                }
        }

        $nPrice = $filters['price'];
        $query = $query->where(function ($q) use ($nPrice) {
            if (!empty($nPrice [0])) {
                $q->where('ads_prices.value', '>=', $nPrice[0]);
            }
            if (!empty($nPrice [1])) {
                $q->where('ads_prices.value', '<=', $nPrice[1]);
            }
        });
        if (!empty($filters['search-filter'])) {
            $filter = $filters['search-filter'];
            $number = preg_replace('~[^0-9]+~', '', $filter);
            if ((preg_replace('~[^0-9\s()+-]+~', '', $filter) == $filter) && (iconv_strlen($number) < 16 && iconv_strlen($number) > 4)) {
                $number = substr($number, 1);
                $query->where('ads_contacts.phone', 'like', '%' . $number . '%')->orwhere('ads.title', 'like', '%' . $filter . '%');
            } else {
                $query->where('ads.title', 'like', '%' . $filters['search-filter'] . '%');

            }
        }

        $oOptions = $this->oListingOptions->keyBy('name');
        $counter = 0;
        $query = $query->where(function($query) use($oOptions, $filters, $refBooksValue, &$counter) {
            foreach ($filters as $key => $value) {
                $option = $oOptions->get($key);
                if (!empty($option) && (!empty($value)) && $key != 'price') {
                    if (is_array($value) && empty($value[0]) && empty($value[1])) {
                        continue;
                    }
                    $query = $query->orWhere(function($query) use($option, $key, $value, $refBooksValue) {
                        $query = $query->where('ads_params.option_id', $option['id']);
                        if (array_search($key, $this->multiple)) {
                            is_array($value) ? $query->whereIn('ads_params.value', $value) : $query->where('ads_params.value', $value);
                        } elseif (is_array($value)) {
                            if (!empty($value[0])) {
                                $query->where('ads_params.value', '>=', $value[0]);
                            }
                            if (!empty($value[1])) {
                                $query->where('ads_params.value', '<=', $value[1]);
                            }
                        } else {
                            $val = array_get(self::MAPPING, $key . '.' . $value, $value);
                            if (preg_replace('~[^0-9]+~', '', $val) != $val) {
                                $aVal = $refBooksValue->where('sysname', $val)->first()->toArray();
                                $val = array_get($aVal, 'id');
                            }
                            $query->where('ads_params.value', $val);
                        }

                        return $query;
                    });

                    $counter++;
                }
            }
        });

       // dd($query->count());
        /*$query = $query->groupBy(['ads.id']);*/
        /*if ($counter !=0) {
            $query ->having(DB::raw('COUNT(ads_params.id)'), '=', $counter);
        }*/
        if (!empty($bounds)) {
            $aIds = $this->getListingsIdsByCoords($bounds);
            if (!empty($aIds)) {
                $query->whereRaw('ads.id IN (' . implode(',', $aIds) . ')');
            }
        }
        return $query;
    }

    /**
     * Получение данных для вывода
     * @param $query
     * @param int $rLimit
     * @param int $rOffset
     * @param int $rPagination
     * @return array
     */
    public function getList($query, $rLimit = 0, $rOffset = 0, $rPagination = 0){
        $limit =($rLimit != 0) ? $rLimit : self::LIMIT;
        $offset = ($rOffset != 0) ? $rOffset :self::OFFSET;
        $count = $query->count();
        $result= $query->orderby('published_at', 'desc');

        if ($limit !== 0) {
            $result= $result->limit($limit)->offset($offset);
        }
        $result = $result->get();
        $oUrl = DB::table('ads_images')->whereIn('ads_id', $result->pluck('ads_id'))
            ->groupBy('ads_id')->get()->keyBy('ads_id');
        $data = $result->toBase()->transform(function($element) use($oUrl){
            //$res = $element->toArray();
            $res = $element;
            $res->price = [
                'type' => isset($element->type) ? $element->type : '',
                'value' => isset($element->value) ? $element->value : '',
                'valute' => isset($element->valute) ? $element->valute : ''
            ];
            $url = array_get($oUrl,$element->id, '');
            if (isset($url->url) && $url != '') {
                $res->preview = $url->url;
            }
            $res->contact_type = [
                'id' => isset($element->person_type) ? $element->person_type : '',
                'name' => ($element->person_type == 2) ? 'Агентство' : "Собственник"
            ];
            $param = DB::table('ads_params')->where([['option_id', '=', 68],['ads_id', '=', $element->id]])->first();
            if (isset($param->value)) {
                $res->deal_type = array_get(self::MAPPING, 'deal_type_name' . '.' . $param->value);
            }
            $type = DB::table('ads_params')->where([['option_id', '=', 66],['ads_id', '=', $element->id]])->first();
            if (isset($type->value)) {
                $res->realty_type = array_get(self::MAPPING, 'realty_type_name' . '.' . $type->value);
            }
            return $res;
        });
        $result = ['data' => $data,
            'count' => $count,
            'offset' => (integer) $offset,
            'limit'=> (integer) $limit
        ];
        if($rPagination != 0) {
            $paginationResult = [
                'first_page_url' => $this->url . '/api/external_listings/list?page=1',
                'from' => $offset +1,
                'last_page' => ceil($count / $limit),
                'last_page_url' => $this->url . '/api/external_listings/list?page=' . ceil($count / $limit),
                'next_page' => $offset / $limit + 2,
                'next_page_url' => $this->url . '/api/external_listings/list?page=' . ($offset /$limit + 2),
                'path' => $this->url . '/api/external_listings/list',
                'per_page' => (integer) $limit ,
                'prev_page_url' => ($offset / $limit == 0) ? null :$this->url . '/api/external_listings/list?page=' . ceil($offset / $limit),
                'to' => $offset + count($data),
                'total' => $count,
                'current_page' => $offset / $limit +1
            ];
            $result['paginationResult'] = $paginationResult;
        }
        return $result;
    }

    /**
     * Поиск по координатам
     * @param $aBounds
     * @return array
     *
     */
    protected function getListingsIdsByCoords($aBounds){
        ini_set('memory_limit','512M');
        $aDataCoords =json_decode(Redis::get('coords'), true);
        $aIds = [];
        $aStartBounds = $aBounds[0];
        $aEndBounds = $aBounds[1];
        if(!empty($aDataCoords)) {
            foreach ($aDataCoords as $aVal) {
                $aCoords = json_decode($aVal['coords'], true);
                if (is_array($aCoords) &&
                    $aCoords['lat'] > $aStartBounds[0] &&
                    $aCoords['lon'] > $aStartBounds[1] &&
                    $aCoords['lat'] < $aEndBounds[0] &&
                    $aCoords['lon'] < $aEndBounds[1]
                ) {
                    $aIds[] = $aVal['id'];
                }
            }
        }

        return $aIds;
    }

}