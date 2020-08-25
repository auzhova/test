<?php

namespace App\Services\Geo;

use App\Models\GeoName;
use App\Services\Geo\Modules\GeoModule;

class Service21Manager extends GeoManager {

    protected $limit = 250;

    public function __construct(){
        parent::__construct();
        $this->provider = $this->getProvider('service21');
    }

    /**
     * Возвращает массив гео-объектов, удовлетворяющих параметрам
     * @param array $parameters
     * @return array
     */
    public function getGeos(array $parameters = []): array {
        $GeoModule = new GeoModule($parameters['type']);
        $data = $this->preparaData($parameters);
        $response = $GeoModule->get($data);
        $result = $this->transformAnswer($data,$response);

        return $this->handleResponse($result);
    }

    /**
     * Возвращает объект , удовлетворяющих параметрам
     * @param array $parameters
     * @return array
     */
    public function getGeoById(array $parameters = []): array {
        $GeoModule = new GeoModule($parameters['type']);
        $data = $this->preparaData($parameters);
        $response = $GeoModule->first($data);
        $result = $this->transformAnswer($data,$response);

        return $this->handleResponse($result);
    }


    /**
     * Возвращает связку массивов гео-объектов, удовлетворяющих связке параметров
     * @param array $bunchOfParameters
     * @return array
     */
    public function getGeosBunch(array $bunchOfParameters = []): array {
        $response = [];
        foreach ($bunchOfParameters as $parameters){
            if(in_array($parameters['type'],['country','district','metro','residential_complex_avito','residential_complex_cian','residential_complex_yandex','residential_complex_emls'])){
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
            if(in_array($parameters['type'],['country','district','metro','residential_complex_avito','residential_complex_cian','residential_complex_yandex','residential_complex_emls'])){
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
//                return $response;

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
        $data = $parameters;
        if($parameters['type'] !== 'country' && (!array_get($parameters,'country') || empty($parameters['country']))){
            $data['country'] = 'RU';
        }
        if(isset($parameters['limit']) && $parameters['limit'] > 999){
            $data['limit'] = 999;
        }elseif(!isset($parameters['limit'])){
            $data['limit'] = $this->limit;
        }
        if(!isset($parameters['data'])){
            $data['data'] = false;
        }
        if(!isset($parameters['parents'])){
            $data['parents'] = false;
        }

        return $data;
    }

    /*
     * Приводим ответ сервиса к требуемому виду данные для запроса
     * @param $response
     * @return mixed
     */
    protected function transformAnswer($data,$response){
        /* тут делаем мапинг */
        $result = ['country' => array_get($data,'country', null), 'list' => []];
        foreach ($response as $value) {
            $res = [
                'id' => array_get($value,'id'),//array_get($value,'fias_id') ? $value['fias_id'] : array_get($value,'internal_id'),
                'value' => array_get($value,'name',array_get($value,'title')),
                'type' => $data['type'],
                'value_unrestricted' => array_get($value,'title') ? array_get($value,'title') : array_get($value,'name'),
                'value_id' => array_get($value,'item_id'),
            ];
            if($data['data']){
                $res['data'] = [
                    'code' => array_get($value,'code'),
                    'kladr_id' => array_get($value,'kladr_id'),
                    'postal_code' => array_get($value,'postal_code'),
                    'okato' => array_get($value,'okato'),
                    'oktmo' => array_get($value,'oktmo'),
                    'capital_marker' => array_get($value,'capital_marker'),
                    'fias_code' => array_get($value,'fias_code'),
                ];
            }
            //нужно будет доделать, для запроса родителя нужен запрос в dadata
            if($data['parents']){
                $res['parents'] = [];
            }
            /*
            $parents = ['region', 'area', 'city', 'city_district', 'settlement', 'street'];
            foreach ($parents as $parent){
                if(array_get($value,'data.'.$parent)){
                    $res['parents'][] = [
                        'type' => $parent,
                        'id' => array_get($value,'data.'.$parent.'_fias_id'),
                        'value' => array_get($value,'data.'.$parent.'_with_type'),
                        'code' => array_get($value,'data.'.$parent.'_iso_code'),
                        'kladr_id' => array_get($value,'data.'.$parent.'_kladr_id'),
                    ];
                }
            }
            */
            $result['list'][] = $res;
        }
        return $result;
    }
}