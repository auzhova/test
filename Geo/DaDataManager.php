<?php

namespace App\Services\Geo;

use App\Jobs\CacheGeoData;
use App\Models\Geo\Country;
use App\Services\Geo;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Class DaDataManager.
 *
 * @package App\Services\Geo
 */
class DaDataManager extends GeoManager {

    protected $limit = 10;

    private $api = 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/';

    public function __construct(){
        parent::__construct();
        $this->provider = $this->getProvider('datata');
    }

    /**
     * Возвращает массив гео-объектов, удовлетворяющих параметрам
     * @param array $parameters
     * @return array
     */
    public function getGeos(array $parameters = []): array {
        /** @var Response $response */
        $url = $this->api.'suggest/address';
        $result = [];
        try{
            $data = $this->preparaData($parameters);
            $response = $this->provider->request($url, $data, 'POST');
            $result = $this->transformAnswer($data,$response);
        }catch (\Exception $exception){
            $aData['request'] = 'Запрос: '.json_encode($parameters);
            $aData['error'] = 'Ошибка - '.$exception->getMessage();
            Mail::raw($aData['request'] .'; '.$aData['error'], function($message)
            {
                $message->to('info@webregul.ru')->cc('uzhova@webregul.ru')->subject('Ошибка при запросе в dadata.ru');
            });
        }

        return $this->handleResponse($result);
    }

    /**
     * Возвращает объект , удовлетворяющих параметрам
     * @param array $parameters
     * @return array
     */
    public function getGeoById(array $parameters = []): array {
        /** @var Response $response */
        $url = $this->api.'findById/address';
        $result = [];
        try {
            $data = ['query' => array_get($parameters, 'query', '')];
            $geoData = (new GeoData())->get($parameters['query']);
            if ($geoData) {
                $response['suggestions'][] = $geoData;
            } else {
                $response = $this->provider->request($url, $data, 'POST');
            }
            $data['country'] = $parameters['country'];
            $data['type'] = $parameters['type'];
            if (!isset($parameters['data'])) {
                $data['data'] = false;
            } else {
                $data['data'] = $parameters['data'];
            }
            if (!isset($parameters['parents'])) {
                $data['parents'] = false;
            } else {
                $data['parents'] = $parameters['parents'];
            }
            if (!isset($parameters['type_to'])) {
                $data['type_to'] = $parameters['type'];
            } else {
                $data['type_to'] = $parameters['type_to'];
            }
            if (isset($parameters['debug'])) {
                $data['debug'] = $parameters['debug'];
            }
            $result = $this->transformAnswer($data,$response);
        }catch (\Exception $exception){
            $aData['request'] = 'Запрос: '.json_encode($parameters);
            $aData['error'] = 'Ошибка - '.$exception->getMessage();
            Mail::raw($aData['request'] .'; '.$aData['error'], function($message)
            {
                $message->to('info@webregul.ru')->cc('uzhova@webregul.ru')->subject('Ошибка при запросе в dadata.ru');
            });
        }

        return $result;
    }


    /**
     * Возвращает связку массивов гео-объектов, удовлетворяющих связке параметров
     * @param array $bunchOfParameters
     * @return array
     */
    public function getGeosBunch(array $bunchOfParameters = []): array {
        $response = [];
        foreach ($bunchOfParameters as $parameters){
            if(!in_array($parameters['type'],['country','district','metro','residential_complex_avito','residential_complex_cian','residential_complex_yandex','residential_complex_emls'])){
                if(isset($parameters['query_id']) && !empty($parameters['query_id'])){
                    $parameters['query'] = $parameters['query_id'];
                    $response[$parameters['type']] = $this->getGeoById($parameters);
                }else{
                    $response[$parameters['type']] = $this->getGeos($parameters);
                }
            }
        }

        return $this->handleResponse($response);
    }

    /**
     * Возвращает связку массивов гео-объектов, удовлетворяющих связке параметров
     * @param array $bunchOfParameters
     * @return array
     */
    public function getGeoBunchById(array $bunchOfParameters = []): array {
        $response = [];
        foreach ($bunchOfParameters as $parameters){
            if(!in_array($parameters['type'],['country','district','metro','residential_complex_avito','residential_complex_cian','residential_complex_yandex','residential_complex_emls'])){
                $response[$parameters['type']] = $this->getGeoById($parameters);
            }
        }

        return $this->handleResponse($response);
    }

    /**
     * Обработчик ответа
     * @param $response
     * @return mixed
     */
    protected function handleResponse($response) {
        switch (true) {
            case \is_array($response):// && array_key_exists('type',$response):
                return $response;

            case $response instanceof RuntimeException:
                return [];

            default;
                return [];
        }
    }

    /*
     * Подготавливаем данные для запроса
     * @param $parameters
     * @return array
     */
    protected function preparaData(array $parameters){
        $data = [];
        $aBounds = ['country' => 'country', 'region' => 'region', 'subregion' => 'area', 'city' => 'city', 'settlement' => 'settlement', 'street' => 'street', 'highway' => 'street', 'house' => 'house'];
        $data['type'] = $parameters['type'];
        if(isset($parameters['limit'])){
            $data['count'] = $parameters['limit'] > 20 ? 20 : (int) $parameters['limit'];
        }else{
            $data['count'] = $this->limit;
        }
        if(!isset($parameters['data'])){
            $data['data'] = false;
        }else{
            $data['data'] = $parameters['data'];
        }
        if(!isset($parameters['parents'])){
            $data['parents'] = false;
        }else{
            $data['parents'] = $parameters['parents'];
        }
        if(!isset($parameters['type_to'])){
            $parameters['type_to'] = $parameters['type'];
            $data['type_to'] = $parameters['type'];
        }else{
            $data['type_to'] = $parameters['type_to'];
        }
        if(isset($parameters['debug'])){
            $data['debug'] = $parameters['debug'];
        }
        $data['restrict_value'] = true;
        foreach ($parameters as $key=>$value){
            switch ($key){
                case 'query': $data['query'] = $value;
                    break;
                case 'query_ids':
                    if(is_array($value)){
                        foreach ($value as $item){
                            if(is_numeric($item)){
                                $data['locations'][0]['kladr_id'] = $item;
                            }else{
                                $data['locations'][0]['fias_id'] = $item;
                            }
                        }
                    }
                case 'type':
                    $data['type'] = $value;
                    $datataType = array_get($aBounds,$value,$value);
                    $data['from_bound'] = ['value' => $datataType];
                    break;
                case 'country':
                    if(!ctype_digit($value)){
                        $valueCountry = $value;
                    }else{
                        $oCountry = Country::query()->find($value);
                        $valueCountry = $oCountry ? $oCountry->code : '*';
                    }
                    $data['locations'][0]['country_iso_code'] = $valueCountry;
                    $data['country'] = $valueCountry;
                    break;
                case 'type_to':
                    $datataTypeTo = array_get($aBounds,$value,$value);
                    $data['to_bound'] = ['value' => $datataTypeTo];
                    break;
                case 'restrict_type':
                    if(array_get($parameters,'country') == "RU" || array_get($parameters,'country') == 2017370){
                        $datataType = array_get($aBounds,$value,$value);
                        $data['locations'][0][$datataType.'_fias_id'] = $parameters['restrict_id'];
                    }else{
                        $data['locations'][0][$value.'_id'] = $parameters['restrict_id'];
                    }
                    break;
            }
        }
        if($data['type'] == 'highway'){
            if($value == 'highway'){
                $data['locations'][0]['street_type_full'] = 'шоссе';
                $data['locations'][1] = $data['locations'][0];
                $data['locations'][1]['street_type_full'] = 'тракт';
                $data['locations'][2] = $data['locations'][0];
                $data['locations'][2]['street_type_full'] = 'автодорога';
                $data['locations'][3] = $data['locations'][0];
                $data['locations'][3]['street_type_full'] = 'трасса';
                $data['locations'][4] = $data['locations'][0];
                $data['locations'][4]['street_type_full'] = 'дорога';
            }
        }
        return $data;
    }

    /*
     * Приводим ответ сервиса к требуемому виду данные для запроса
     * @param $parameters
     * @param $response
     * @return mixed
     */
    protected function transformAnswer($data,$response){
        /* тут делаем мапинг */
        $parents = ['region', 'area', 'city', 'city_district', 'settlement', 'street'];
        if($data['type_to'] == 'house'){
            $parents[] = 'house';
        }
        $result = ['country' => array_get($data,'country', 'RU'), 'list' => []];
        foreach ($response['suggestions'] as $suggestion) {
            $nId = array_get($suggestion,'data.geoname_id') ? array_get($suggestion,'data.geoname_id') : array_get($suggestion,'data.fias_id');
            if(!$nId){
                continue;
            }
            if($data['type'] == 'city' && $data['type_to'] == 'settlement' &&
                in_array($nId,['0c5b2444-70a0-4932-980c-b4dc0d3f02b5','c2deb16a-0330-4f05-821f-1d09c93331e6','6fdecb78-893a-4e3f-a5ba-aa062459463b'])){
                continue;
            }
            //dispatch((new CacheGeoData($suggestion))->onQueue('geo-data'));
            (new GeoData())->put($suggestion);
            $res = [
                    'id' => $nId,
                    'value' => array_get($suggestion,'value'),
                    'type' => $data['type'],
                    'value_unrestricted' => array_get($suggestion,'unrestricted_value'),
                    'value_list' => array_get($suggestion,'value'),
            ];
            if(is_numeric($res['id'])){
                $res['id'] = (int) $res['id'];
            }

            $resData = [
                'code' => array_get($suggestion,'data.country_iso_code'),
                'kladr_id' => array_get($suggestion,'data.kladr_id'),
                'postal_code' => array_get($suggestion,'data.postal_code'),
                'okato' => array_get($suggestion,'data.okato'),
                'oktmo' => array_get($suggestion,'data.oktmo'),
                'capital_marker' => array_get($suggestion,'data.capital_marker'),
                'fias_code' => array_get($suggestion,'data.fias_code'),
            ];
            if($data['data']){
                $res['data'] = $resData;
            }

            $resParents = [];
            foreach ($parents as $key=>$parent){
                $parentId = array_get($suggestion,'data.'.$parent.'_fias_id');
                if($parent == 'city' && in_array($parentId,['0c5b2444-70a0-4932-980c-b4dc0d3f02b5','c2deb16a-0330-4f05-821f-1d09c93331e6','6fdecb78-893a-4e3f-a5ba-aa062459463b'])){
                    continue;
                }
                if(array_get($suggestion,'data.'.$parent)){
                    $parentType = ($parent == 'area') ? 'subregion' : $parent;
                    $value = ($parentType != 'house') ? array_get($suggestion,'data.'.$parent.'_with_type') : array_get($suggestion,'data.'.$parent.'_type').' '.array_get($suggestion,'data.'.$parent);
                    if($parent == 'area'){
                        $value = array_get($suggestion,'data.region_with_type') ?
                            array_get($suggestion,'data.region_with_type').', '.array_get($suggestion,'data.'.$parent.'_with_type') : array_get($suggestion,'data.'.$parent.'_with_type');
                    }
                    if($parent == 'settlement' && !in_array($suggestion['data']['city_fias_id'],['0c5b2444-70a0-4932-980c-b4dc0d3f02b5','c2deb16a-0330-4f05-821f-1d09c93331e6','6fdecb78-893a-4e3f-a5ba-aa062459463b'])){
                        $value = array_get($suggestion,'data.city_with_type') ?
                            array_get($suggestion,'data.city_with_type').', '.array_get($suggestion,'data.'.$parent.'_with_type') : array_get($suggestion,'data.'.$parent.'_with_type');
                    }
                    $unrestrictedValue = [];
                    foreach ($parents as $k=>$p){
                        $v = $p != 'house' ? array_get($suggestion,'data.'.$p.'_with_type') : array_get($suggestion,'data.'.$p.'_type').' '.array_get($suggestion,'data.'.$p);
                        if($key >= $k && $v){
                            $unrestrictedValue[] = $v;
                        }
                    }
                    if(is_null($parentId)){
                        $url = $this->api.'suggest/address';
                        $resParent = $this->provider->request($url, [
                            'query' => array_get($suggestion,'data.'.$parent.'_with_type'),
                            'locations' => [
                                ['country' => array_get($suggestion,'data.country_iso_code')],
                                [$parent => array_get($suggestion,'data.'.$parent)],
                            ],
                            'to_bound' => ['value' => $parent],
                        ], 'POST');
                        if(array_get($resParent,'suggestions')){
                            (new GeoData())->put($resParent['suggestions'][0]);
                            //dispatch((new CacheGeoData($resParent['suggestions'][0]))->onQueue('geo-data'));
                        }
                        $parentId = array_get($resParent,'suggestions.0.data.geoname_id');
                    }

                    $resParents[] = [
                        'type' => $parentType,
                        'id' => is_numeric($parentId) ? (int)$parentId : $parentId,
                        'value' => $value,
                        'value_unrestricted' => $unrestrictedValue ? implode(', ',$unrestrictedValue) : $value,
                        'code' => array_get($suggestion,'data.'.$parent.'_iso_code'),
                        'kladr_id' => array_get($suggestion,'data.'.$parent.'_kladr_id'),
                        'value_type' => array_get($suggestion,'data.'.$parent.'_type'),
                        'value_clear' => array_get($suggestion,'data.'.$parent),
                    ];
                }
            }
            if($resParents){
                $count = count($resParents);
                $lastParent = $resParents[$count-1];
                if($count && !array_get($lastParent, 'id')){
                    $res['id'] = $resParents[($count-2)]['id'];
                    $res['type'] = $resParents[($count-2)]['type'];
                    $res['value'] = $resParents[($count-2)]['value'];
                    $res['value_unrestricted'] = $resParents[($count-2)]['value_unrestricted'];
                    $res['value_list'] = $resParents[($count-2)]['value_unrestricted'];
                    unset($resParents[$count-1]);
                }
            }
            if($data['parents']){
                $res['parents'] = $resParents;
            }
            $aParents = array_get($res,'parents',$resParents);
            if($aParents){
                $count = count($resParents);
                $lastParent = $resParents[$count-1];
                $res['type'] = array_get($lastParent, 'type',$res['type']);
                if(!($data['type'] == 'region' && $data['type_to'] == 'house')){
                    $res['value'] = array_get($lastParent, 'value',$res['value']);
                }
                if($data['type'] == 'highway' || $data['type'] == 'street'){
                    $count = count($aParents);
                    if($sParent = array_get($aParents,($count-1).'.value')){
                        $res['value_list'] = $sParent.', '.$res['value'];
                    }
                }
            }
            $result['list'][] = $res;
        }
        if(isset($data['debug'])){
            $result['response'] = $response;
        }
        return $result;
    }
}