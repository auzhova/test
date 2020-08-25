<?php

namespace App\Services\Geo\Modules;


use App\Models\Geo\District;

class DistrictModule {

    protected $query;

    public function __construct(){
        $this->query = District::where('status',1);
    }

    /*
     * Получаем массив коллекций по входным данным
     * @param array parameters
     */
    public function get(array $parameters) : array{
        $query = $this->query;
        foreach ($parameters as $key=>$value){
            switch ($key){
                /*
                case 'query_ids' : $query = $query->whereIn('internal_id', $value);
                    break;
                */
                case 'query' :
                    if(is_numeric($value)){
                        $query = $query->where('internal_id', $value);
                    }else{
                        $query = $query->where(function($q) use ($value) {
                            $q->where('id', $value);
                            $q->orWhere('title', 'like', '%'.$value.'%');
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
}