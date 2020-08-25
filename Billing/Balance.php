<?php

namespace App\Services\Billing;

use App\Models\Balance as BalanceMod;
use App\Models\BalancesLimit;
use Cache;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class Balance
{
    private $balance = null; // текущий баланс
    private $balance_id = 0;
    private $user_id = 0;
    public $message = '';

    private $test = false;


    public function __construct(int $balance_id,int $user_id)
    {
        $this->balance_id = $balance_id;
        $this->balance = BalanceMod::find($this->balance_id);

        $this->user_id = $user_id;
        //исключение, что юзер и баланс принадлежат одному агенству

    }

    /*
     * Создание баланса
     * param array $aData
     * return object
     */
    public function create($aData)
    {
        $this->balance = BalanceMod::create($aData);
        return $this->balance ;
    }

    /*
     * Получение баланса
     * param integer $id
     * return object
     */
    public function get() // получение баланса
    {
        return $this->balance;
    }

    /*
     * Получение информации по балансу по юзеру
     * return object
     */
    public function getInfo()
    {
        return $this->withLimit();
    }

    /*
     * Пополнение баланса
     * param float $amount
     * return array
     */
    public function setReplenishment($amount)
    {
        $result = [];
        if($this->check()){
            $balance = $this->balance->balance + floatval($amount);
            $this->balance->update(['balance'=>$balance]);
            $result = ['result' => true];
        }else{
            $result = ['result' => false, 'message' => "Ошибка.Зачисление не выполнено!"];
        }
        return $result;
    }
    
    /*
     * Списание с баланса
     * param float $amount
     * return array
     */
    public function setCancellation($amount)
    {
        $result = [];
        Log::info('setCancellation: amount = '.$amount);
        if($this->check($amount)){
            $balance = $this->balance->balance - floatval($amount);
            $this->balance->update(['balance'=>$balance]);
            $result = ['result' => true];
        }else{
            Log::info('check false');
            Log::info($this->balance);
            $result = ['result' => false, 'message' => $this->message];//"Ошибка.Списание не выполнено!"];
        }
        return $result;
    }
    
    /*
     * Проверка по балансу
     * param float $amount
     * return bool
     */
    public function check($amount = null) : bool
    {
        $bCheck = true;
        if(empty($this->balance)){
            $this->message = 'Баланс не найден.';
            return false;
        }

        if(!empty($amount)){
            $aBalance = $this->getInfo($this->user_id);
            Log::info('check Balance user_id = '.$this->user_id);
            Log::info($aBalance);
            if($aBalance['balance'] >= $amount){
                $bCheck = true;
                if($aBalance['remain'] < $amount){
                    $this->message = sprintf('Недостаточно средств на остатке (%s р.) для списания (%s р.). Израсходован месячный лимит (%s р.)',
                            number_format($aBalance['remain'], 2, ',', ''),
                            number_format($amount, 2, ',', ''),
                            number_format($aBalance['limit'], 2, ',', ''));
                    $bCheck = false;
                }
            }else{
                $this->message = sprintf('Недостаточно средств на балансе (%s р.) для списания (%s р.)',
                        number_format($aBalance['balance'], 2, ',', ''),
                        number_format($amount, 2, ',', ''));
                $bCheck = false;
            }
        }

        return $bCheck;
    }

    /*
     * Создание платежа
     * param array $aData
     * return array
     */
    public function payment($aData, $externalData = []) : array
    {
        $payment = (new Payment());
        $oPayment = $payment->create($aData);
        if(empty($oPayment)){
            return ['result'=>false,'message'=>'Ошибка при создании платежа.'];
        }
        if (!empty($externalData)) {
            $oPayment->update([
                'external_data' => $externalData
            ]);
        }
        $payment->close();
        $aData['type_id'] = (int)$aData['type_id'];
        if (in_array($aData['type_id'],[1,2])) {
            $aChange = $this->setReplenishment($aData['amount']);
        } else {
            $aChange = $this->setCancellation($aData['amount']);
        }
        if (!$aChange['result']) {
            $payment->status(3);
            return ['result'=>false,'message'=>$aChange['message']];
        }else{
            $payment->status(1);
            return ['result'=>true,'payment_id'=>$oPayment->id,'status'=>2];
        }
        /*
        $bClosePayment = (new Payment($oPayment->id))->close();
        Log::info('close payment result '.$bClosePayment);
        if ($bClosePayment) {
            if (!$this->test) {
                $aData['type_id'] = (int)$aData['type_id'];
                if (in_array($aData['type_id'],[1,2])) {
                    $aChange = $this->setReplenishment($aData['amount']);
                } else {
                    $aChange = $this->setCancellation($aData['amount']);
                }
            } else {
                $aData['type_id'] = (int)$aData['type_id'];
                if (in_array($aData['type_id'],[1,2])) {
                    $aChange = $this->setReplenishment($aData['amount']);
                } else {
                    $aChange = $this->setCancellation($aData['amount']);
                }
            }
            if (!$aChange['result']) {
                $oPayment->status = 3;
                $oPayment->save();
                return ['result'=>false,'message'=>$aChange['message']];
            }
            return ['result'=>true,'payment_id'=>$oPayment->id,'status'=>2];
        }else{
            return ['result'=>false,'payment_id'=>$oPayment->id,'status'=>3];
        }
        */
    }

    public function externalData()
    {

    }

    public function withLimit()
    {
        $sStartMonth = Carbon::now()->firstOfMonth();
        $sEndMonth = Carbon::now()->endOfMonth();
        $oAgent = \App\Models\Agent::where('user_id',$this->user_id)->first();
        $nAmount = \App\Models\Payment::whereBetween('created_at', [$sStartMonth,$sEndMonth])
                    ->where('balance_id',$this->balance->id)
                    ->where('agent_id',$oAgent->id)
                    ->where('amount', '<', 0)->where('status',1)->sum('amount');//списания с минусом
        $nAmount = abs($nAmount);
        $nRemain = '0.0';//остаток
        $oUser = \App\Models\User::where('id',$this->user_id)->with('role_user.role')->first();
        $oBalanceLimit = null;
        if(!in_array($oUser->role_user->role->slug, ['broker','superbroker'])){
            $oBalanceLimit = BalancesLimit::where('balance_id',$this->balance->id)->where('user_id',$this->user_id)->where('status',1)->first();
        }
        if(!empty($oBalanceLimit)) {
            $nLimit = $oBalanceLimit->limit;
            if ($this->balance->balance > $nLimit) {
                $nRemain = $nLimit - $nAmount;
            } else {
                $nRemain = $this->balance->balance;
            }
        }else{//безлимит
            $nLimit = null;
            $nRemain = $this->balance->balance;
        }
        $aBalance = [
            'id'        =>$this->balance->id,
            'item_id'   =>$this->balance->item_id,
            'type'      =>$this->balance->type,
            'balance'   =>$this->balance->balance,
            'limit'     =>$nLimit,
            'remain'    =>$nRemain,
            'amount'    =>$nAmount
        ];
        return $aBalance;
    }

    public function runTest()
    {
        $this->test = true;
    }
}