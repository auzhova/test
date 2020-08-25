<?php

namespace App\Api\Project\Geo;

use App\Api\Core\BaseController;
use App\Models\Geo\District;
use App\Services\Geo;
use App\Services\Geo\DaDataManager;
use App\Services\Geo\Service21Manager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\Geo\GeoManager;

/**
 * Class GeoV2Controller.
 *
 * @package App\Api\Project\Geo
 */
class GeoV2Controller extends BaseController {

    /**
     * @var GeoManager
     */
    private $geoManager;

    private $restrictType = [
        'country' => null,
        'region' => 'country',
        'subregion' => 'region',
        'city' => 'subregion',
        'settlement' => 'region',
        'district' => 'city',
        'metro' => 'city',
        'street' => 'city',
        'highway' => 'region',
        'residential_complex_avito' => 'region',
        'residential_complex_cian' => 'region',
        'residential_complex_yandex' => 'region',
        'residential_complex_emls' => 'region'
    ];

    /**
     * GeoV2Controller constructor.
     */
    public function __construct() {
        //$this->geoManager = $geoManager;
        parent::__construct();
    }

    /**
     * Возвращает коллекцию гео
     * @param Request $request
     * @return JsonResponse
     * @desc Универсальный метод на получение гео-объектов. Возможные параметры (список может изменяться):
     *  - type - обязательный параметр, string; тип гео, по которому производится поиск;
     *  - type_to - не обязательный параметр, string; по умолчанию равно type;
     *  - country - обязательный параметр, string|int; id либо символьный iso код страны. для запроса стран передается null
     *  - query - не обязательный параметр, string; поисковая строка, максимум 20 символов; если не передано поиск по всем элементам. Для всех типов кроме стран, районов, метро - обязательно;
     *  - query_id - не обязательный параметр, string; значение идентификатора для поиска;
     *  - query_ids - не обязательный параметр, array; массив со значениями идентификаторов для поиска;
     *  - limit - не обязательный параметр, int; количество результатов, по умолчанию 10, для запросов с типом country, district, metro - максимум 999 и дефолт 200, для остальных максимум 20 и дефолт 10
     *  - data - не обязательный параметр, bool; флаг необходимости добавления в ответ расширенных данных. По умолчанию false
     *  - parents - не обязательный параметр, bool; флаг необходимости добавления в ответ дерева со всеми родительскими элементами; по умолчанию false
     *  - boost - поднятие значения при сортировке. на проработке. позже.
     */
    public function get(Request $request): JsonResponse {
        $response = ['success' => true];
        $aRequest = $request->input();
        if(!isset($aRequest['type'])){
            $response['error'][] = 'Не передан обязательный параметр type';
            $response['success'] = false;
        }

        if(!isset($aRequest['country'])){
            $response['error'][] = 'Не передан обязательный параметр country';
            $response['success'] = false;
        }elseif($aRequest['type'] !== 'country' && !preg_match('/^[a-zA-Z0-9_]+$/i', $aRequest['country'])){
            $response['error'][] = 'Параметр country не должен быть пустым';
            $response['success'] = false;
        }

        if(!isset($aRequest['query']) && isset($aRequest['type']) &&
            in_array($aRequest['type'],['country'])){
            $response['error'][] = 'Не передан обязательный параметр query';
            $response['success'] = false;
        }

        if(!isset($aRequest['restrict_id']) && isset($aRequest['restrict_type'])){
            $response['error'][] = 'Не передан обязательный параметр restrict_id';
            $response['success'] = false;
        }elseif(isset($aRequest['restrict_id']) && (!isset($aRequest['restrict_type']) || empty($aRequest['restrict_type']) || !preg_match('/^[a-zA-Z]+$/i', $aRequest['restrict_type']))){
            $aRequest['restrict_type'] = array_get($this->restrictType, $aRequest['type']);
        }

        if(!$response['success']){
            return new JsonResponse($response, 400);
        }

        if(in_array($aRequest['type'],['country','district','metro','residential_complex_avito','residential_complex_cian','residential_complex_yandex','residential_complex_emls'])){
            $this->geoManager = new Service21Manager();
        }else{
            $this->geoManager = new DaDataManager();
        }

        if(isset($aRequest['query_id']) && !empty($aRequest['query_id'])){
            $aRequest['query'] = $aRequest['query_id'];
            $response = $this->geoManager->getGeoById($aRequest);
        }else{
            $response = $this->geoManager->getGeos($aRequest);
        }

        return new JsonResponse($response, 200);
    }

    /**
     * Возвращает коллекцию по коду КЛАДР или ФИАС
     * @param Request $request
     * @return JsonResponse
     * @desc Универсальный метод на получение гео-объектов. Возможные параметры (список может изменяться):
     *  - query - обязательный параметр, string|int; поисковая строка;
     *  - type - обязательный параметр, string; тип гео, по которому производится поиск;
     *  - data - не обязательный параметр, bool; флаг необходимости добавления в ответ расширенных данных. По умолчанию false
     *  - parents - не обязательный параметр, bool; флаг необходимости добавления в ответ дерева со всеми родительскими элементами; по умолчанию false
     */
    public function one(Request $request): JsonResponse {
        $response = ['success' => true];
        $aRequest = $request->input();
        if(!isset($aRequest['type'])){
            $response['error'][] = 'Не передан обязательный параметр type';
            $response['success'] = false;
        }

        if(!isset($aRequest['query'])){
            $response['error'][] = 'Не передан обязательный параметр query';
            $response['success'] = false;
        }

        if(!$response['success']){
            return new JsonResponse($response, 400);
        }

        if(in_array($aRequest['type'],['country','district','metro','residential_complex_avito','residential_complex_cian','residential_complex_yandex','residential_complex_emls'])){
            $this->geoManager = new Service21Manager();
        }else{
            $this->geoManager = new DaDataManager();
        }

        $response = $this->geoManager->getGeoById($aRequest);

        return new JsonResponse($response, 200);
    }

    /**
     * Получение множества коллекций гео-объектов
     * @param Request $request
     * @return JsonResponse
     * @desc Под капотом - множественный вызов функционала метода get. Возможные параметры (список может измениться):
     *  - bunch - обязательный параметр, array; каждый элемент которого - параметры метода get
     */
    public function bunch(Request $request): JsonResponse {
        $response = ['success' => true];
        $aRequest = $request->input();
        if(!isset($aRequest['bunch'])){
            $response['error'][] = 'Не передан обязательный параметр bunch';
            $response['success'] = false;
        }
        if(!$response['success']){
            return new JsonResponse($response, 400);
        }
        $serviceResponse = (new Service21Manager())->getGeosBunch($aRequest['bunch']);
        $dadataResponse = (new DaDataManager())->getGeosBunch($aRequest['bunch']);
        //$response = array_merge($serviceResponse,$dadataResponse);
        $response = [];
        foreach ($aRequest['bunch'] as  $bunch){
            if(in_array($bunch['type'],['country','district','metro','residential_complex_avito','residential_complex_cian','residential_complex_yandex','residential_complex_emls'])){
                $response[] = array_get($serviceResponse,$bunch['type']);
            }else{
                $response[] = array_get($dadataResponse,$bunch['type']);
            }
        }

        return new JsonResponse($response, 200);
    }

    /**
     * Получение множества коллекций гео-объектов
     * @param Request $request
     * @return JsonResponse
     * @desc Под капотом - множественный вызов функционала метода get. Возможные параметры (список может измениться):
     *  - bunch - обязательный параметр, array; каждый элемент которого - параметры метода one
     */
    public function oneBunch(Request $request): JsonResponse {
        $response = ['success' => true];
        $aRequest = $request->input();
        if(!isset($aRequest['bunch'])){
            $response['error'][] = 'Не передан обязательный параметр bunch';
            $response['success'] = false;
        }
        if(!$response['success']){
            return new JsonResponse($response, 400);
        }
        $serviceResponse = (new Service21Manager())->getGeoBunchById($aRequest['bunch']);
        $dadataResponse = (new DaDataManager())->getGeoBunchById($aRequest['bunch']);
        //$response = array_merge($serviceResponse,$dadataResponse);
        $response = [];
        foreach ($aRequest['bunch'] as  $bunch){
            if(in_array($bunch['type'],['country','district','metro','residential_complex_avito','residential_complex_cian','residential_complex_yandex','residential_complex_emls'])){
                $response[] = array_get($serviceResponse,$bunch['type']);
            }else{
                $response[] = array_get($dadataResponse,$bunch['type']);
            }
        }

        return new JsonResponse($response, 200);
    }

    /**
     * Получение координат по адресу
     * @param Request $request
     * @return JsonResponse
     * @desc Универсальный метод на получение координат по текстовому адресу
     *  - query - обязательный параметр, string; поисковая строка;
     */
    public function coords(Request $request): JsonResponse {
        $response = ['success' => true];
        $aRequest = $request->input();
        if(!isset($aRequest['query'])){
            $response['error'][] = 'Не передан обязательный параметр query';
            $response['success'] = false;
        }
        if(!$response['success']){
            return new JsonResponse($response, 400);
        }
        $response = Geo::getCoordsYandex($aRequest['query']);

        return new JsonResponse($response, 200);
    }

    /**
     * Добавление районов
     * @param Request $request
     * @return JsonResponse
     * @desc Метод для добавления района в базу
     *  - title - обязательный параметр, string; название;
     *  - parent_id - обязательный параметр, string; фиас ид родителя;
     */
    public function setDistrict(Request $request): JsonResponse {
        $response = ['success' => true];
        $aRequest = $request->input();
        if(!isset($aRequest['title']) || !isset($aRequest['parent_id'])){
            $response['error'][] = 'Не передан обязательный параметр title или parent_id';
            $response['success'] = false;
        }
        if(!$response['success']){
            return new JsonResponse($response, 400);
        }
        $aData = [
            'title' => $aRequest['title'],
            'fias_id' => array_get($aRequest,'fias_id', ''),
            'parent_id' => $aRequest['parent_id'],
            'okato' => array_get($aRequest,'okato', ''),
        ];
        $response = District::updateOrCreate(['title' => $aRequest['title'], 'parent_id' => $aRequest['parent_id']],$aData);

        return new JsonResponse($response, 200);
    }

    /**
     * Определение ближайшего района и метро
     * @param Request $request
     * @return JsonResponse
     * @desc
     *  - address - обязательный параметр, string; адрес объекта;
     *  - coords - необязательный параметр, string; координаты;
     */
    public function getNearestInfo(Request $request): JsonResponse {
        $response = ['success' => true];
        $aRequest = $request->input();
        if(!isset($aRequest['address'])){
            $response['error'][] = 'Не передан обязательный параметр address';
            $response['success'] = false;
        }
        if(!$response['success']){
            return new JsonResponse($response, 400);
        }
        $geo = new Geo();
        $response['district'] = $geo->getNearDistrict($aRequest['address']);

        $response['metro'] = $geo->getNearMetro($aRequest['address'],array_get($aRequest,'coords'));

        return new JsonResponse($response, 200);
    }
}