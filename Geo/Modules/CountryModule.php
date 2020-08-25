<?php

namespace App\Services\Geo\Modules;


use App\Models\Geo\Country;

class CountryModule {

    protected $query;

    public function __construct(){
        $this->query = Country::where('status',1);
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
                        $query = $query->where('id', $value);
                    }else{
                        $query = $query->where(function($q) use ($value) {
                            $q->where('name', 'like', '%'.$value.'%');
                            $q->orWhere('title', 'like', '%'.$value.'%');
                            $q->orWhere('code', 'like', $value);
                        });
                    }
                    break;
                default : break;
            }
        }
        return $query->take($parameters['limit'])->orderBy('priority','desc')->orderBy('name','asc')->get()->toArray();
    }

    /*
     * Получаем коллекцию по входным данным
     * @param array parameters
     */

    public function first(array $parameters) : array{
        $query = $this->query;
        if(is_numeric($parameters['query'])){
            $query = $query->where('id', $parameters['query']);
        }else{
            $query = $query->where('code', 'like', $parameters['query']);
        }
        return $query->get()->toArray();
    }

}