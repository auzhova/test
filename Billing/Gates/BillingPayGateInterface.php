<?php

namespace App\Services\Billing\Gates;


interface BillingPayGateInterface
{

    /**
     * Проверка шлюза
     *
     * @return array ['result' => true|false]
     */
    public function check() : array;

    /**
     * Идентификатор платежной системы
     *
     * @return string
     */
    public function code() : string;

    public function failUrl() : string;

    public function successUrl() : string;

    /**
     * Получение url
     *
     * @return array ['result' => true|false]
     */
    public function url() : array;

    /**
     * Обработка уведомления
     * из данных надо выбрать:
     * - result - true|false
     * - invoice_id - id счета
     * - amount - полученная сумма
     * - payment_id - id внутреннего платежа (не нашего)
     *
     * @param $aData ['invoice_id' => ..., 'amount' => ..., 'payment_id' => ...]
     * @return array
     */
    public function notification($aData) : array;

    /**
     * Действия с возвратом
     *
     * @param $paymentId
     * @return array ['result' => true|false]
     */
    public function cancel($paymentId = null) : array;

    /**
     * Подтвердить платеж
     *
     * @param $paymentId
     * @return array ['result' => true|false]
     */
    public function accept($paymentId = null) : array;

}