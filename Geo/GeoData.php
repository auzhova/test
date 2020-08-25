<?php

namespace App\Services\Geo;


use Illuminate\Support\Facades\Cache;
use App\Models\Geo\GeoData as GeoDataMod;

class GeoData
{

    public function __construct(){

    }

    /*
     * Получение гео данных по ид
     * @param string|integer $id
     * @return array
     */

    public function get($id){
        $response = [];
        $sHash = md5($id);
        if($cacheGeoData = Cache::tags('geodata')->get($sHash)){
            return Cache::tags('geodata')->get($sHash);
        }
        $record = GeoDataMod::find($id);
        if($record){
            $response = !is_array($record->value) ? json_decode($record->value,true) : $record->value;
            Cache::tags('geodata')->remember($sHash, 1440, function() use($response) {
                return $response;
            });
        }
        return $response;
    }

    /*
     * Добавление гео данных в кеш и базу
     * @param array $data
     * @return array
     */

    public function put($data){
        if(array_get($data,'data.house')){
            return $data;
        }
        $id = array_get($data,'data.geoname_id') ? array_get($data,'data.geoname_id') : array_get($data,'data.fias_id');
        $sHash = md5($id);
        if($data){
            Cache::tags('geodata')->remember($sHash, 1440, function() use($data) {
                return $data;
            });
        }
        GeoDataMod::unguard();
        GeoDataMod::updateOrCreate(
            [
                'id' => $id
            ],
            [
                'id' => $id,
                'value' => json_encode($data)
            ]);
        GeoDataMod::reguard();
        return $data;
    }
}