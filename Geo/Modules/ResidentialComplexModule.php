<?php

namespace App\Services\Geo\Modules;


use App\Models\Geo\ResidentalComplex;

class ResidentialComplexModule {

    protected $query;

    public function __construct(){
        $this->query = ResidentalComplex::where('status',1);
    }

    /*
     * Получаем массив коллекций по входным данным
     * @param array parameters
     */
    public function get(array $parameters) : array{
        $query = $this->query;
        $area = explode('_',$parameters['type']);
        $parameters['area'] = array_pop($area);
        foreach ($parameters as $key=>$value){
            switch ($key){
                case 'area' :
                    $query = $query->where('area', 'like', $value);
                    break;
                case 'query' :
                    if(is_numeric($value)){
                        $query = $query->where('id', $value);
                    }else {
                        if (strpos($value,'-feed-') !== false){
                            $value = str_replace('-feed-','', $value);
                            $query = $query->where('name', 'like', $value . '%');
                        }else{
                            $query = $query->where('name', 'like', '%' . $value . '%');
                        }
                    }
                    break;
                case 'restrict_type':
                    $query = $query->where(function ($q) use ($parameters) {
                        $q->orWhere('city_id', $parameters['restrict_id']);
                        $q->orWhere('region_id', $parameters['restrict_id']);
                    });
                    break;
                default : break;
            }
        }
        return $query->orderBy('name','asc')->take($parameters['limit'])->get()->toArray();
    }

    /*
     * Получаем коллекцию по входным данным
     * @param array parameters
     */

    public function first(array $parameters) : array{
        $query = $this->query->where('id', $parameters['query']);
        return $query->get()->toArray();
    }
}