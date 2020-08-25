<?php

namespace App\Services\Billing;


interface BillingInterface
{
    /**
     * Включить режим теста
     */
    public function runTest() : void;

    /**
     * Присвоить $this->data $data
     *
     * @param array $data
     */
    public function data(array $data) : void;

    public function open();

    public function close();

}