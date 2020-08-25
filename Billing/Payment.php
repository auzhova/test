<?php

namespace App\Services\Billing;

use App\Models\Payment as PaymentMod;
use Carbon\Carbon;

class Payment
{
    private $payment = null; // текущий платеж
    private $payment_id = 0; // ид платежа


    public function __construct(int $payment_id = 0)
    {
        $this->payment_id = $payment_id;
        $this->payment = PaymentMod::whereId($payment_id)->first();
    }


    /*
     * Получение текущего платежа
     * param integer $id
     * return object
     */
    public function get($id)
    {
        return $this->payment;
    }
    
    /*
     * Создание платежа
     * param array $aData
     * return object
     */
    public function create($aData) : PaymentMod
    {
        $aData['date_create'] = time();
        $aData['date_close'] = time();
        $aData['status'] = 1;
        $aData['created_at'] = Carbon::now();
        $aData['updated_at'] = $aData['created_at'];
        $nTypeId = (int)$aData['type_id'];
        if(!in_array($nTypeId,[1,2])){
            $aData['amount'] = -$aData['amount'];
        }
        $this->payment = PaymentMod::create($aData);
        return $this->payment;
    }
    
    /*
     * Закрытие платежа
     * return object
     */
    public function close()
    {
        $nStatus = 3; // неуспешный платеж
        if ($this->check()) {
            $nStatus = 1; // успешный платеж
        }
        $this->payment->update([
            'status' => $nStatus
        ]);
        return ($this->payment->status === 1) ? true : false;
    }
    
    /*
     * Проверка совершения платежа
     * return bool
     */
    public function check() : bool
    {
        $bCheck = false;
        return $bCheck;
    }

    /*
     * Изменение статуса платежа
     * return object
     */
    public function status($nStatus)
    {
        $this->payment->update([
            'status' => $nStatus
        ]);
        return true;
    }
}