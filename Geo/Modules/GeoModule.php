<?php

namespace App\Services\Geo\Modules;


use Illuminate\Support\Str;

class GeoModule {

    protected $GeoModule;

    protected $sModelPath = '\App\Services\Geo\Modules\\';

    public function __construct(string $nameModule){
        if(in_array($nameModule,['residential_complex_avito','residential_complex_cian','residential_complex_yandex','residential_complex_emls'])){
            $nameModule = 'residential_complex';
        }
        $nameModule = Str::camel($nameModule);
        $sModule = $this->sModelPath.ucfirst($nameModule).'Module';
        $this->GeoModule = new $sModule();
    }

    /*
     * Получаем массив коллекций по входным данным
     * @param array parameters
     */
    public function get(array $parameters = []) :array {
        if($parameters){
            return $this->GeoModule->get($parameters);
        }else{
            return [];
        }

    }

    /*
     * Получаем коллекцию по входным данным
     * @param array parameters
     */
    public function first(array $parameters = []) :array {
        if($parameters){
            return $this->GeoModule->first($parameters);
        }else{
            return [];
        }

    }
}