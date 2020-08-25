<?php

namespace App\Services\Billing\Gates;

use App\Http\Requests\Request;

class Gate21online extends BillingGate implements BillingPayGateInterface, BillingSaleGateInterface
{
    public $key = '21online';
    
    public function check() : array
    {
        $ip =\Illuminate\Support\Facades\Request::ip();
        if(!in_array($ip,config('services.billing.21online.ip'))){
            $this->error("IP ".$ip." не допустим");
        }
        return $this->success();
    }
    
    public function notification($aData) : array
    {
        return $this->success([
            'invoice_id' => array_get($aData,'invoice_id'),
            'amount' => array_get($aData,'amount'),
            'amount_without_commission' => array_get($aData,'amount'),
            'payment_id' => isset($aData['payment_id']) ? $aData['payment_id'] : null,
            'type_id' => isset($aData['type_id']) ? $aData['type_id'] : null
        ]);
    }
    
    public function url() : array
    {

        $i = md5($this->invoice->id.$this->invoice->amount);
        $url = url('/balance/p/view-invoice/?i=');
        return $this->success([
            'url' => $url.$i
        ]);
    }
    
    public function failUrl() : string
    {
        return url('/balance');
    }
    
    public function successUrl() : string
    {
        $i = md5($this->invoice->id.$this->invoice->amount);
        $url = url('/balance/p/view-invoice/?i=');
        return $url.$i;
    }


    public function code() : string
    {
        return "ОО";
    }

    public function cancel($paymentId = null) : array
    {
        return $this->success();
    }

    public function accept($paymentId = null) : array
    {
        return $this->success();
    }


}