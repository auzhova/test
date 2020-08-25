<?php

namespace App\Services\Geo\Providers;

use RuntimeException;

/**
 * Class Service21Provider.
 * @desc Реализация внутреннего интерфейса для сервиса 21online
 *
 * @package Services\Geo\Providers
 */
class Service21Provider implements GeoProviderInterface {

    use GeoProviderTrait {
        request as requestTrait;
    }

    /**
     * {@inheritdoc}
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function request(string $uri, array $params = [], string $method = 'GET') {

    }
}