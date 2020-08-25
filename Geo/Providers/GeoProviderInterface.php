<?php

namespace App\Services\Geo\Providers;

use GuzzleHttp\Client;

/**
 * Interface GeoProvider.
 * @desc Интерфейс адаптера для получения гео-данных от внешнего АПИ. Нужен для объединения общих сущностей в одно семейство.
 *
 * @package Services\Geo\Providers
 */
interface GeoProviderInterface {

    /**
     * Выполняет запрос к внешнему АПИ.
     *
     * @param string $uri
     * @param array $params
     * @param string $method
     * @return mixed
     */
    public function request(string $uri, array $params = [], string $method = 'GET');
}