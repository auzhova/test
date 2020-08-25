<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\RefBooksValue;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class SearchAdsView implements SearchAdsInterface
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
    protected $multiple;


    public function __construct() {
        $this->url = env('PAGE_URL', url('/'));
        $this->multiple = self::MULTIPLE;
    }


    /**
     * Фильтрация данных
     * @param $filters
     * @param  $bounds
     * @return object|bool
     */
    public function filter($filters, $bounds){
        $query = DB::table('ads_view');

        $refBooksValue = RefBooksValue::where('status', 1)->where('sysname', '!=', '');
        if (!empty($bounds)) {

            $aIds = $this->getListingsIdsByCoords($bounds);
            if (!empty($aIds)) {
                $query->whereRaw('id IN (' . implode(',', $aIds) . ')');
            } else {
              return false;
            }
        }
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
                    $query->where('person_type', '<>', 2);
                    break;
                }
            case '0' :
                {
                    $query->where('person_type', '=', 2);
                    break;
                }
        }
        $nPrice = $filters['price'];
        $query = $query->where(function ($q) use ($nPrice) {
            if (!empty($nPrice [0])) {
                $q->where('price', '>=', $nPrice[0]);
            }
            if (!empty($nPrice [1])) {
                $q->where('price', '<=', $nPrice[1]);
            }
        });
        if (!empty($filters['search-filter'])) {
            $filter = $filters['search-filter'];
            $number = preg_replace('~[^0-9]+~', '', $filter);
            if ((preg_replace('~[^0-9\s()+-]+~', '', $filter) == $filter) && (iconv_strlen($number) < 16 && iconv_strlen($number) > 4)) {
                $number = substr($number, 1);
                $query->where('phone', 'like', '%' . $number . '%')->orwhere('title', 'like', '%' . $filter . '%');
            } else {
                $query->where('title', 'like', '%' . $filters['search-filter'] . '%');
            }
        }
        //$counter=0;
        $aOptions=['deal_type', 'realty_type', 'realty_subtype', 'region', 'is_new_building', 'all_area', 'city', 'number_of_rooms', 'district'];
        $query = $query->where(function($query) use($aOptions, $filters, $refBooksValue, &$counter) {
            foreach ($filters as $key => $value) {
                $option = in_array($key, $aOptions);
                if ($option === true && (!empty($value)) && $key != 'price') {
                   if (is_array($value) && empty($value[0]) && empty($value[1])) {
                        continue;
                    }
                    $query = $query->Where(function($query) use( $key, $value, $refBooksValue) {

                        if (array_search($key, $this->multiple)) {
                            is_array($value) ? $query->whereIn($key, $value) : $query->where($key, $value);

                        } elseif (is_array($value)) {
                            if (!empty($value[0])) {
                                $query->where($key, '>=', $value[0]);
                            }
                            if (!empty($value[1])) {
                                $query->where($key, '<=', $value[1]);
                            }
                        } else {
                            $val = array_get(self::MAPPING, $key . '.' . $value, $value);
                            if (preg_replace('~[^0-9]+~', '', $val) != $val) {
                                $aVal = $refBooksValue->where('sysname', $val)->first()->toArray();
                                $val = array_get($aVal, 'id');
                            }
                            $query = $query->where($key, $val);
                        }
                        return $query;
                    });


                   // $counter++;
                }
            }
        });
        //$query = $query->groupBy(['id']);
        /*if ($counter !=0) {
            $query ->having(DB::raw('COUNT(ads_params.id)'), '=', $counter);
        }*/
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
        $result= $query->orderby('published_at', 'desc');
        $count = $result->count(); //получаем количество
        if ($limit !== 0) {
            $result= $result->limit($limit)->offset($offset);
        }
        $result = $result->get(); //получаем заявки
        $oUrl = DB::table('ads_images')->whereIn('ads_id', $result->pluck('id'))
            ->groupBy('ads_id')->get()->keyBy('ads_id'); //получаем url
        //параметры для вывода
        $data = $result->toBase()->transform(function (&$e) use($oUrl){
        $id = $e->id;
        $queryGetList = DB::table('ads')->select('address',  'ads_prices.type', 'ads_prices.value', 'ads_prices.valute',
              'ads_contacts.person_type')->where('ads.id', $id)
            ->LeftJoin('ads_prices', 'ads.id', '=', 'ads_prices.ads_id')
              ->LeftJoin('ads_contacts', 'ads_contacts.id', '=', 'ads.contact_id')
        ->first();
        $e->address = isset($queryGetList->address) ? $queryGetList->address : '';
        $e->price = [
                'type' => isset($queryGetList->type) ? $queryGetList->type : '',
                'value' => isset($queryGetList->value) ? $queryGetList->value : '',
                'valute' => isset($queryGetList->valute) ? $queryGetList->valute : ''
         ];
        $url = array_get($oUrl,$e->id, '');
        if (isset($url->url) && $url != '') {
                $e->preview = $url->url;
         }
        $e->contact_type = [
            'id' => isset($queryGetList->person_type) ? $queryGetList->person_type : '',
            'name' => ($queryGetList->person_type == 2) ? 'Агентство' : "Собственник"
        ];
            $param = DB::table('ads_params')->where([['option_id', '=', 68],['ads_id', '=', $e->id]])->first();
            if (isset($param->value)) {
                $e->deal_type = array_get(self::MAPPING, 'deal_type_name' . '.' . $param->value);
            }
            $type = DB::table('ads_params')->where([['option_id', '=', 66],['ads_id', '=', $e->id]])->first();
            if (isset($type->value)) {
                $e->realty_type = array_get(self::MAPPING, 'realty_type_name' . '.' . $type->value);
            }
            return $e;
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
        $mRadius = array_get($aBounds, 'radius_m', 100);
        ini_set('memory_limit','512M');
        $aIds = [];
        $aStartBounds = $aBounds[0];
        $aEndBounds = $aBounds[1];

        $middleLat = ($aStartBounds[0] + $aEndBounds[0])/2; //x
        $middleLon = ($aStartBounds[1] + $aEndBounds[1])/2; //y

        $aDataCoords = Redis::geoRadius('coords', $middleLon,$middleLat, $mRadius,'m', 'WITHCOORD');
        if(!empty($aDataCoords)) {
            foreach ($aDataCoords as $aData) {
                $aCoords = array_get($aData, 1);
                $id = array_get($aData, 0);
                if (is_array($aCoords) && !empty($aCoords) &&
                    $aCoords[1] > $aStartBounds[0] &&
                    $aCoords[0] > $aStartBounds[1] &&
                    $aCoords[1] < $aEndBounds[0] &&
                    $aCoords[0] < $aEndBounds[1]
                ) {
                    $aIds[] = $id;
                }
            }
        }
        return $aIds;
    }
}