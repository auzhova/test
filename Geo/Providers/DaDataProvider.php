<?php

namespace App\Services\Geo\Providers;

use RuntimeException;

/**
 * Class DaDataProvider.
 * @desc Реализация внешнего интерфейса для сервиса dadata.ru
 *
 * @package Services\Geo\Providers
 */
class DaDataProvider implements GeoProviderInterface {

    use GeoProviderTrait {
        request as requestTrait;
    }

    /**
     * {@inheritdoc}
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function request(string $uri, array $params = [], string $method = 'GET') {
        $authToken = config('services.dadataru.token');
        if (!$authToken) {
            # Действия, если токена нет

            return new RuntimeException('Токен dadata.ru не найден.', 401);
        }

        return $this->requestTrait($uri, $params, [
            'Authorization' => "Token $authToken",
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json'
        ], $method);
    }
}