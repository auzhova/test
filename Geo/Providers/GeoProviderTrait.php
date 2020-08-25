<?php

namespace App\Services\Geo\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use RuntimeException;

/**
 * Trait GeoProviderTrait.
 *
 * @package Services\Geo\Providers
 */
trait GeoProviderTrait {

    /**
     * @var Client
     */
    private $httpClient;

    /**
     * Выполняет HTTP-запрос к указанному АПИ.
     *
     * @param string $uri
     * @param array $params
     * @param array $headers
     * @param string $method
     * @param Client|null $httpClient
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function request(string $uri, array $params = [], array $headers = [], string $method = 'POST', ?Client $httpClient = null) {
        $method = strtoupper($method);
        # 0. Если нет клиента HTTP - создаем
        if ($httpClient) {
            $this->httpClient = $httpClient;
        } elseif (!$this->httpClient) {
            $this->httpClient = new Client();
        }

        [$method, $options] = [strtoupper($method), [
//            'verify' => false
        ]];

        # 1. Добавляем заголовки
        if ($headers) {
            $options['headers'] = $headers;
        }

        # 1. В зависимости от метода определяем структуру опций
        if ($params) {
            switch (true) {
                case $method === 'GET':
                    $options['query'] = $params;
                    break;

                case array_key_exists('Content-Type', $headers) && strtolower($headers['Content-Type']) === 'application/json':
                    $options['json'] = $params;
                    break;

                default:
                    # form-data не поддерживается, все привожу к x-www-form-urlencoded
                    $options['form_params'] = $params;
                    break;
            }
        }

        try {
            $response = $this->httpClient->request($method, trim($uri), $options);
            if ($response->getStatusCode() === 200 && $response->getReasonPhrase() === 'OK') {
                $content        = $response->getBody()->getContents();
                $contentDecoded = json_decode($content, true);

                return json_last_error() === JSON_ERROR_NONE ? $contentDecoded : $content;
            }

            return new RuntimeException($response->getReasonPhrase(), $response->getStatusCode());

        } catch (BadResponseException $exception) {
            # Можно сделать логирование
            return $exception->getResponse();
        }
    }
}