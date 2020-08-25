<?php

namespace App\Services\Geo;

use App\Services\Geo\Providers\DaDataProvider;
use App\Services\Geo\Providers\GeoProviderInterface;
use App\Services\Geo\Providers\Service21Provider;

/**
 * Class GeoManager.
 * @desc Менеджер для работы с провайдерами гео
 *
 * @package App\Services\Geo
 */
abstract class GeoManager {

    protected $providers = [
        'service21'  => Service21Provider::class,
        'datata'     => DaDataProvider::class,
    ];

    /**
     * @var GeoProviderInterface
     */
    protected $provider;

    /**
     * GeoManager constructor.
     *
     */
    public function __construct() {
    }

    /**
     * @return GeoProviderInterface
     */
    public function getProvider(string $name): GeoProviderInterface {
        $this->provider = $this->providers[$name];

        return app($this->provider);
    }

    /**
     * @param GeoProviderInterface $provider
     * @return GeoManager
     */
    public function setProvider(GeoProviderInterface $provider): GeoManager {
        $this->provider = $provider;

        return $this;
    }

    /**
     * Возвращает массив гео-объектов, удовлетворяющих параметрам
     * @param array $parameters
     * @return array
     */
    abstract public function getGeos(array $parameters = []): array;

    /**
     * Возвращает связку массивов гео-объектов, удовлетворяющих связке параметров
     * @param array $bunchOfParameters
     * @return array
     */
    abstract public function getGeosBunch(array $bunchOfParameters = []): array;

    /**
     * Возвращает массив гео-объектов, удовлетворяющих связке параметров
     * @param array $parameters
     * @return array
     */
    abstract public function getGeoById(array $parameters = []): array;

    /**
     * Возвращает массив гео-объектов, удовлетворяющих связке параметров
     * @param array $parameters
     * @return array
     */
    abstract public function getGeoBunchById(array $parameters = []): array;
}