<?php

namespace App\Services\Billing\Gates;


abstract class BillingGate
{
    /**
     * Счет для шлюзов
     *
     * @var null
     */
    protected $invoice = null;

    /**
     * Заказ для шлюзов
     *
     * @var null
     */
    protected $order = null;

    /**
     * Идентификатор теста в шлюзах
     *
     * @var bool
     */
    protected $test = false;

    /**
     * Контакты данные для шлюзов
     * - email
     * - phone
     * - description
     *
     * @var array
     */
    protected $contacts = [];


    /**
     * Добавление Счета в Шлюзы
     *
     * @param $oInvoice
     * @return $this
     */
    public function invoice($oInvoice)
    {
        $this->invoice = $oInvoice;

        return $this;
    }

    /**
     * Добавление дополнительных данных в Шлюзы
     *
     * @param $contacts
     * @return $this
     */
    public function setContacts($contacts)
    {
        $this->contacts = $contacts;

        return $this;
    }

    /**
     * Добавление Заказа в Шлюзы
     *
     * @param $oOrder
     * @return $this
     */
    public function setOrder($oOrder)
    {
        $this->order = $oOrder;

        return $this;
    }

    /**
     * Вернуть успех
     *
     * @param array $data
     * @return array
     */
    protected function success($data = [])
    {
        return array_merge([
            'result' => true,
        ], $data);
    }

    /**
     * Вернуть все плохо и почему
     *
     * @param $message
     * @param array $data
     * @param $status
     * @return array
     */
    protected function error($message, $data = [], $status = 400)
    {
        return array_merge([
            'result' => false,
            'message' => $message,
            'status' => $status
        ], $data);
    }

    public function runTest()
    {
        $this->test = true;
    }


}