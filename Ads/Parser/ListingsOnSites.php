<?php
/**
 * Created by PhpStorm.
 * User: Nastya
 * Date: 18.12.2018
 * Time: 19:46
 */

namespace App\Services\Parser;

use App\Models\AdsContact;
use App\Models\History;
use App\Services\Geo;
use Carbon\Carbon;
use Cache;
use App\Models\AdsOption;
use App\Models\Ads;
use App\Models\AdsParam;
use App\Models\AdsPrice;
use App\Models\AdsImage;

class ListingsOnSites
{
    public $parser;

    public $created_count = 0;

    public $updated_count = 0;

    public function __construct($parser = null)
    {
        if($parser == null) {
            $this->parser = (new AdsParser())->get();
        }else{
            $this->parser = $parser;
        }
        Cache::forget('AdsOptions');
        $aOptions = Cache::remember('ListingsOptions', 10000, function() {
            return AdsOption::whereStatus(1)->get()->toArray();
        });
        $this->oListingOptions = collect($aOptions);
        $this->aGeoParams = ["country","region","subregion","city","district","metro","highway","street","house", "block","building","apartment_number", "address"];
    }

    /*
     * Записываем полученные от парсера объявления в БД
     */
    public function setObjects(){
        $oRecords = $this->parser;
        foreach ($oRecords as $oRecord){
            if(empty($oRecord['ads']['external_id'])){
                continue;
            }
            $nId = $this->saveListing($oRecord);
            $this->saveContact($nId,$oRecord);
            $this->saveParams($nId,$oRecord);
            $this->savePrice($nId,$oRecord);
            $this->saveImages($nId,$oRecord);
        }
        return [
            'result' => true,
            'created' => $this->created_count,
            'updated' => $this->updated_count,
        ];
    }

    public function saveListing($aData){
        $oListing = Ads::where('external_id',$aData['ads']['external_id'])->first();
        if(!empty($oListing)){
            if($oListing->address != $aData['ads']['address']){
                $aData['ads']['status'] = 0;
                $aData['ads']['geo_status'] = 0;
                /*
                $aCoords = json_decode($aData['ads']['coords'],true);
                if(empty(array_get($aCoords,'lat')) || empty(array_get($aCoords,'lon',array_get($aCoords,'lng')))){
                    $aCoords = Geo::getCoordsYandex($aData['ads']['address']);
                }
                $aAnwser = Geo::getYandex(implode(',',[array_get($aCoords,'lon',array_get($aCoords,'lng')),array_get($aCoords,'lat')]));
                if($aAnwser){
                    $this->saveGeoParams($oListing->id,$aAnwser,$aCoords);
                }
                */
            }
            $dPublushed = $aData['ads']['published_at'];
            unset($aData['ads']['published_at']);
            if($oListing->published_at > $dPublushed){
                $aData['ads']['published_at'] = $dPublushed;
            }
            Ads::where('id',$oListing->id)->update($aData['ads']);
            //$this->setHistory('listing',$oListing->id,2,'Обновление', $aData);
            $this->updated_count++;
        }else{
            $oListing = Ads::create($aData['ads']);
            //$this->setHistory('listing',$oListing->id,1,'Создание', $aData);
            $this->created_count++;
        }
        return $oListing->id;
    }


    public function saveContact($nId, $aData){
        if(!array_get($aData,'contact.name')){
            $aData['contact']['name'] = array_get($aData,'contact.phone','no_name');
        }
        if(preg_match('/[,.:;!@&?0-9a-zA-zа-яА-я]/u', $aData['contact']['name'])){
            $clearName = preg_replace('/[^,.:;!@&?0-9a-zA-zа-яА-я]/u', '', $aData['contact']['name']);
            $aData['contact']['name'] = $clearName.$aData['contact']['phone'];
        }
        $oContact = AdsContact::updateOrCreate(
            [
                'person_type' => $aData['contact']['person_type'],
                'name' => $aData['contact']['name'],
                'phone' => $aData['contact']['phone'],
            ],
            [
                'person_type' => $aData['contact']['person_type'],
                'name' => $aData['contact']['name'],
                'phone' => $aData['contact']['phone'],
                'contactname' => $aData['contact']['contactname'],
                'count_ads' => ($aData['contact']['count_ads']) ? $aData['contact']['count_ads'] : 0,
            ]
        );
        Ads::where('id',$nId)->update(['contact_id'  => $oContact->id]);
    }


    public function saveGeoParams($nId, $aData, $aCoords)
    {
        $oGeo = new Geo();
        $aComponents = array_get($aData,'0.GeoObject.metaDataProperty.GeocoderMetaData.Address.Components',[]);
        if($aComponents){
        $map = [
            'country'  => 'country',
            'province' => 'region',
            'area'     => 'subregion',
            'locality' => 'city',
            'district' => 'district',
            'metro'    => 'metro',
            'street'   => 'street',
            'house'    => 'house'
        ];
        $res = [];
        $districts = [];
        $metros = [];
        if (!empty($aComponents)) {
            foreach ($aComponents as $aComponent) {
                $key = array_get($map, $aComponent['kind']);
                if ($key) {
                    if ((strpos($aComponent['name'], 'округ') !== false && ($aComponent['kind'] == 'province' || $aComponent['kind'] == 'area' || $aComponent['kind'] == 'district'))
                    || (strpos($aComponent['name'], 'жилой комплекс') !== false && $aComponent['kind'] == 'district')) {
                        continue;
                    }
                    $res[$key] = $aComponent['name'];
                    if($key == 'district'){
                        $districts[] = $aComponent['name'];
                    }
                    if($key == 'metro'){
                        $metros[] = $aComponent['name'];
                    }
                }
            }
        }
        $aOption = [];
        if(!isset($res['district'])){
            $aYandDistrict = $this->getYandexDistrict($aCoords);
            if($aYandDistrict){
                $res['district'] = array_get($aYandDistrict,0);
                if(count($aYandDistrict) > 1){
                    $districts = $aYandDistrict;
                }
            }
        }
        if(!isset($res['metro'])){
            $aYandMetro = $this->getYandexMetro($aCoords);
            if($aYandMetro){
            $res['metro'] = array_get($aYandMetro,0);
                if(count($aYandMetro) > 1){
                    $metros = $aYandMetro;
                }
            }
        }
        if ($res) {
            foreach ($res as $key => $value) {
                switch ($key) {
                    case 'country' :
                        $mod = 'im';
                        $ct = 'C';
                        $parent = 0;
                        $wp = 0;
                        $fiasId = $oGeo->getFiasId($key, $mod, $ct, $parent, $wp, $value);
                        $aOption[$key] = $fiasId;
                        break;
                    case 'region' :
                        $mod = 'si';
                        $ct = 1;
                        $parent = 0;
                        $wp = 0;
                        $fiasId = $oGeo->getFiasId($key, $mod, $ct, $parent, $wp, $value);
                        $aOption[$key] = $fiasId;
                        break;
                    case 'subregion' :
                        $mod = 'si';
                        $ct = 3;
                        $parent = array_get($aOption, 'region', 0);
                        $wp = 1;
                        $fiasId = $oGeo->getFiasId($key, $mod, $ct, $parent, $wp, $value);
                        $aOption[$key] = $fiasId;
                        break;
                    case 'city' :
                        $mod = 'si';
                        $ct = 2;
                        $parent = (array_get($aOption, 'country', 2017370) == 2017370)
                            ? (array_get($aOption, 'subregion',0) ? array_get($aOption, 'subregion') : array_get($aOption, 'region', 0))
                            : array_get($aOption, 'country');
                        $wp = 4;
                        $fiasId = $oGeo->getFiasId($key, $mod, $ct, $parent, $wp, $value);
                        $aOption[$key] = $fiasId;
                        break;
                    case 'street' :
                        $mod = 'si';
                        $ct = 4;
                        $parent = (array_get($aOption, 'city', 0) > 0)
                            ? array_get($aOption, 'city')
                            : array_get($aOption, 'region', 0);
                        $wp = 5;
                        $fiasId = $oGeo->getFiasId($key, $mod, $ct, $parent, $wp, $value);
                        $aOption[$key] = $fiasId;
                        break;
                    case 'district' :
                        $mod = 'si';
                        $ct = 7;
                        $parent = (array_get($aOption, 'city', 0) > 0)
                            ? array_get($aOption, 'city')
                            : array_get($aOption, 'region', 0);
                        $wp = 0;
                        if($districts){
                            $fias = [];
                            foreach($districts as $item){
                                $fiasid = $oGeo->getFiasId($key, $mod, $ct, $parent, $wp, $item);
                                if(!empty($fiasid)){
                                    $fias[] = $fiasid;
                                }
                            }
                            $fiasId = array_get($fias,0,0);
                        }else{
                            $fiasId = $oGeo->getFiasId($key, $mod, $ct, $parent, $wp, $value);
                        }
                        $aOption[$key] = $fiasId;
                        break;
                    case 'metro' :
                        $mod = 'si';
                        $ct = 6;
                        $parent = (array_get($aOption, 'city', 0) > 0)
                            ? array_get($aOption, 'city')
                            : array_get($aOption, 'region', 0);
                        $wp = 0;
                        if($metros){
                            $fias = [];
                            foreach($metros as $item){
                                $fiasid = $oGeo->getFiasId($key, $mod, $ct, $parent, $wp, $item);
                                if(!empty($fiasid)){
                                    $fias[] = $fiasid;
                                }
                            }
                            $fiasId = $fias;
                        }else{
                            $fiasId = $oGeo->getFiasId($key, $mod, $ct, $parent, $wp, $value);
                        }
                        $aOption[$key] = $fiasId;
                        break;
                }
            }
        }
        $aOption['address'] = implode(', ', $res);
        $aOption['house'] = array_get($res,'house','');
        $aQueryData = [];
        $aOptionsId = [];
        foreach ($aOption as $key => $value) {
            if ($value === '' || $value === false) {
                continue;
            }
            $nOptionId = $this->oListingOptions->where('name', $key)->pluck('id')->get(0);
            if ($nOptionId) {
                if(is_array($value)){
                    foreach ($value as $item) {
                        $aParam = [
                            'ads_id' => $nId,
                            'option_id' => $nOptionId,
                            'value' => $item,
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now()
                        ];
                    }
                }else{
                    $aParam = [
                        'ads_id' => $nId,
                        'option_id' => $nOptionId,
                        'value' => $value,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ];
                }
                $aOptionsId[] = $nOptionId;
                $aQueryData[] = $aParam;
            }
        }
        if($aQueryData){
            AdsParam::where('ads_id',$nId)->whereIn('option_id',$aOptionsId)->delete();
            AdsParam::insert($aQueryData);
            $sCoords = json_encode($aCoords);
            Ads::where('id', $nId)->update(['coords' => $sCoords,'status' => 1,'geo_status' => 1]);
        }
        }else{
            //$this->setHistory('listing',$nId,2,'Ошибка гео', $aData);
        }
    }

    public function getYandexDistrict($aCoords)
    {
        $aAnwser = Geo::getYandexDistrict(implode(',', [array_get($aCoords, 'lon', array_get($aCoords, 'lng')), array_get($aCoords, 'lat')]));
        $aComponents = array_get($aAnwser, '0.GeoObject.metaDataProperty.GeocoderMetaData.Address.Components', []);
        $result = [];
        foreach ($aComponents as $aComponent) {
            if ($aComponent['kind'] != 'district') {
                continue;
            }
            if ((strpos($aComponent['name'], 'округ') !== false) || (strpos($aComponent['name'], 'жилой комплекс') !== false)) {
                    continue;
            }
            $result[] = $aComponent['name'];
        }
        return $result;
    }

    public function getYandexMetro($aCoords)
    {
        $aAnwser = Geo::getYandexMetro(implode(',', [array_get($aCoords, 'lon', array_get($aCoords, 'lng')), array_get($aCoords, 'lat')]));
        $aComponents = array_get($aAnwser, '0.GeoObject.metaDataProperty.GeocoderMetaData.Address.Components', []);
        $result = [];
        foreach ($aComponents as $aComponent) {
            if ($aComponent['kind'] != 'metro') {
                continue;
            }
            $result[] = $aComponent['name'];
        }
        return $result;
    }

    public function getGeoParams($nId, $aData){
        $Geo = new Geo();
        $aAddress = Geo::getYandex($aData['params']['address']);
        //dd($aAddress);
        $adress = $aAddress['0']['GeoObject']['metaDataProperty']['GeocoderMetaData']['AddressDetails']['Country']['AdministrativeArea'];
        $sRegion = $adress['AdministrativeAreaName'];
        $sRegion = trim(str_replace(['область', 'край', 'Руспублика', 'автономная область', 'посёлок'], '', $sRegion));
        $nRegion = $Geo->getRegionId($sRegion);
        dd($adress);
        if(isset($adress['SubAdministrativeArea'])) {
            $sCity = $adress['SubAdministrativeArea']['Locality']['LocalityName'];
        }else{
            $sCity = $adress['Locality']['LocalityName'];
        }
        $nCity = $Geo->getCityId($nRegion, $sCity);
        $sArea =  $adress['SubAdministrativeArea']['SubAdministrativeAreaName'];
        $sArea = trim(str_replace(['городской округ'], '',$sArea));
        $nArea = $Geo->getAreaId($nRegion, $sArea);
            $sStreet = $adress['SubAdministrativeArea']['Locality']['Thoroughfare']['ThoroughfareName'];
            $sStreet = trim(str_replace(['улица', 'переулок'], '', $sStreet));
            $nStreet = $Geo->getStreetId($nCity, $sStreet);
        dd($sRegion, $nRegion,$sCity, $nCity,$sArea, $nArea,  $sStreet, $nStreet);

    }

    public function saveParams($nId,$aData){
        if($aData['params']){
            unset($aData['params']['coords']);
            $aQueryData = [];
            $aOptionsId = [];
            foreach ($aData['params'] as $key=>$value){
                if($value === '' || $value === false){
                    continue;
                }
                $nOptionId = $this->oListingOptions->where('name', $key)->pluck('id')->get(0);
                if($nOptionId){
                    $aParam = [
                        'ads_id'       => $nId,
                        'option_id'    => $nOptionId,
                        'value'        => $value,
                        'created_at'   => Carbon::now(),
                        'updated_at'   => Carbon::now()
                    ];
                    $aQueryData[] = $aParam;
                    $aOptionsId[] = $nOptionId;
                }
            }
            if($aQueryData){
                AdsParam::where('ads_id',$nId)->whereIn('option_id',$aOptionsId)->delete();
                AdsParam::insert($aQueryData);
            }
        }
    }

    public function savePrice($nId, $aData){
        $sPriceType = 'all';
        $dSquare = (double) array_get($aData, 'params.all_area', 0);
        $dPriceAll = (double) array_get($aData, 'price.value');
        if($dSquare !=0) {
            $dPriceM = (double)array_get($aData, 'price.value') / $dSquare;
        }else{
            $dPriceM = (double)array_get($aData, 'price.value');
        }

        $aPrice = [
            'ads_id'    => $nId,
            'type'      => $sPriceType,
            'value'     => $dPriceAll,
            'value_m'   => $dPriceM,
            'period'    => array_get($aData, 'price.period',''),
        ];
        AdsPrice::updateOrCreate(['ads_id'=>$nId], $aPrice);
    }

    public function saveImages($nId, $aData){
        if(array_get($aData,'images')){
            AdsImage::where('ads_id',$nId)->delete();
            foreach ($aData['images'] as $url){
                AdsImage::create(['ads_id'=>$nId, 'url'=>$url]);
            }
        }
    }

    public function setHistory($sType,$nItemId,$sAction,$sComment,$aLog){
        History::create([
            'type' => $sType,
            'item_id' => $nItemId,
            'action_id' => $sAction,
            'comment' => $sComment,
            'log' => json_encode($aLog),
            ]);
    }
}