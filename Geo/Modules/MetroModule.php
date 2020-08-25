<?php

namespace App\Services\Geo\Modules;


use App\Models\Geo\Metro;
use App\Services\Geo\GeoData;
use Illuminate\Support\Facades\DB;

class MetroModule {

    protected $query;

    protected $regions = [
        '0c5b2444-70a0-4932-980c-b4dc0d3f02b5' => '0c5b2444-70a0-4932-980c-b4dc0d3f02b5', //Москва
        //'29251dcf-00a1-4e34-98d4-5c47484a36d4', //МО
        'c2deb16a-0330-4f05-821f-1d09c93331e6' => 'c2deb16a-0330-4f05-821f-1d09c93331e6', //Спб
        '6d1ebb35-70c6-4129-bd55-da3969658f5d' => 'c2deb16a-0330-4f05-821f-1d09c93331e6', //ЛО
        ];

    public function __construct(){
        $this->query = Metro::where('status',1);
    }

    /*
     * Получаем массив коллекций по входным данным
     * @param array parameters
     */
    public function get(array $parameters) : array{
        $query = $this->query;
        $geoData = (new GeoData())->get($parameters['restrict_id']);
        if($geoData && $sRegionFiasId = array_get($geoData, 'data.region_fias_id')){
            if(array_get($this->regions,$sRegionFiasId)){
                $parameters['restrict_id'] = $sRegionFiasId;
            }
        }
        /*
        if(isset($parameters['restrict_id'])){
            if($parameters['restrict_id'] == '0c5b2444-70a0-4932-980c-b4dc0d3f02b5'){ //москва
                $parameters['restrict_id'] = '0c5b2444-70a0-4932-980c-b4dc0d3f02b5';
            }elseif($parameters['restrict_id'] == 'c2deb16a-0330-4f05-821f-1d09c93331e6' || $parameters['restrict_id'] == '6d1ebb35-70c6-4129-bd55-da3969658f5d'){ //Спб и ЛО
                $parameters['restrict_id'] = 'c2deb16a-0330-4f05-821f-1d09c93331e6';
            }
        }
        */
        foreach ($parameters as $key=>$value){
            switch ($key){
                /*
                case 'query_ids' : $query = $query->whereIn('internal_id', $value);
                    break;
                */
                case 'query' :
                    if(is_numeric($value)){
                        $query = $query->where('internal_id', $value);
                    }else {
                        $query = $query->where(function ($q) use ($value) {
                            $q->where('id', $value);
                            $q->orWhere('title', 'like', '%' . $value . '%');
                        });
                    }
                    break;
                case 'restrict_type':
                    $query = $query->where('parent_id', $parameters['restrict_id']);
                    break;
                default : break;
            }
        }
        return $query->take($parameters['limit'])->get()->toArray();
    }

    /*
     * Получаем коллекцию по входным данным
     * @param array parameters
     */

    public function first(array $parameters) : array{
        $query = $this->query;
        if(is_numeric($parameters['query'])){
            $query = $query->where('internal_id', $parameters['query']);
        }else {
            $query = $query->where('id', $parameters['query']);
        }
        return $query->get()->toArray();
    }

    /*
     * Получаем ближайшее метро по координатам
     * @param array coords
     */

    public function coords(array $coords) : array{
        $query = $this->query;
        $query = $query->select('*',DB::raw('(SQRT(POW(('.$coords['lat'].' - metros.coords->>"$.lat"), 2) + POW(('.$coords['lon'].' - metros.coords->>"$.lon"), 2))) as DST'))
                    ->whereNotNull('coords')->where('coords','!=','')->orderBy("DST");
        return $query->take(1)->get()->toArray();
    }
}