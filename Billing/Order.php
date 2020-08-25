<?php

namespace App\Services\Billing;

use App\Models\Agent;
use App\Models\PaymentsOrder;
use App\Models\Balance as BalanceMod;
use App\Services\Billing\Gates\BillingSaleGateInterface;
use App\Services\Billing\Balance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class Order implements BillingInterface
{
    private $db = null;
    /**
     * Модель Order текущий заказ
     *
     * @var
     */
    private $order;

    /**
     * Текущий шлюз
     *
     * @var
     */
    private $gateway;
    
    /**
     * Настройки для Шлюзов
     * - min_pay
     * - max_pay
     * - expires
     *
     * @var array
     */
    private $config = [];

    /**
     * Экземпляр для логирования
     *
     * @var BillingLogger|null
     */
    private $log = null;
    
    /**
     * Данные:
     * - open() ['creater_id' => ..., 'balance_id' => ..., 'amount' => ...]
     * - * request->all()
     *
     * @var array
     */
    private $data = [];

    /**
     * Сообщение при ошибке, отлавливается на выходе с ошибкой
     *
     * @var string
     */
    public $message = '';

    /**
     * Назначение платежа
     *
     * @var string
     */
    public $purpose = '';


    /**
     * Сегодняшняя дата и время Carbon::now()
     *
     * @var null|static
     */
    private $now = null;

    /**
     * Идентификатор тестирования
     *
     * @var bool
     */
    private $test = false;

    /**
     * Статус уведомлений после неуспешной проверки
     *
     * @var int
     */
    protected $notificationStatus = 400;

    private $aRepTypes = [
        1 => 'Пополнение баланса',
        2 => 'Пополнение по гарантийному письму',
    ];

    private $aCancelTypes = [
        3 => 'Списание за рекламу',
        4 => 'Списание абонентской платы',
        5 => 'Списание по гарантийному письму',
        6 => 'Списание за выписку из ЕГРН',
        7 => 'Списание за выписку из ЕГРН о переходе прав',
        8 => 'Списание за рекламу (ускоренная публикация)',
    ];


    public function __construct(BillingSaleGateInterface $gateway)
    {
        $this->log = new BillingLogger();
        
        $this->gateway = $gateway; //  присвоить определенный Gate
        $this->db = new PaymentsOrder;

        $this->now = Carbon::now();
        
        $this->config['expires'] = config('billing.' . $this->gateway->key . '.expires');
    }


    /*
     * Открыть заказ
     * param 
     * return 
     */
    public function open()
    {
        $this->before();
        
        $this->log->info(__FUNCTION__);

        if (!$this->checkForOpen()) {
            return false;
        }

        $this->order = $this->create($this->data);

//        $this->generateCode();

        $this->setStatus(array_keys($this->db->statuses)[1]);
        
        $result = ($this->order) ? ['result' => true, 'url' => $this->order->id] : ['result' => false, 'message' => 'Ошибка при создании заказа'];
//        $result = $this->gateway->setOrder($this->order)->url();

        if (!$result['result']) {
            $this->message = $result['message'];
            $this->log->info($result['message']);
            $this->setStatus(array_keys($this->db->statuses)[3]);
        }
        return isset($result['url']) ? $result['url'] : false;
        
    }

    /**
     * Скрипты до выполнения методов, например
     * - включить режим теста в шлюзах
     */
    private function before() : void
    {
        if ($this->test) {
            $this->gateway->runTest();
        }
    }

    /**
     * Присвоить данные, отсутствие каких либо параметров невозможно
     * [
     *  'creator_id' => ...,
     *  'balance_id' => ...,
     *  'amount' => ...,
     * ]
     *
     * @param array $data
     */
    public function data(array $data) : void
    {
        $this->data = $data;
    }

    /*
     * Проверка при открытие заказа
     * param 
     * return array ['result' => true|false, 'message' => '']
     */
    private function checkForOpen()
    {
        $check = $this->gateway->check();
        return $this->returnMessage($check);
    }
    
    /*
     * Создать заказ
     * param array $data
     * return object
     */
    private function create($data) : PaymentsOrder
    {
        $data['payment_id'] = 0;
        $data['gateway'] = $this->gateway->key;
        $data['status'] = array_keys($this->db->statuses)[0];
        $data['opened_at'] = $this->now;
        $data['expires_at'] = $this->now->copy()->addDays($this->config['expires']);
        return $this->db->create($data);
    }

    
    /*
     * Получить урл для оплаты через external data по своему
     * return $string
     */
    private function getUrl() : string
    {
        $url = $this->gateway->url();
        return $url['url'];
    }

    /**
     *
     */
    public function close()
    {
        $this->before();
        
        $this->log->info(__FUNCTION__);

        $this->log->info('data', $this->data);

        $aExternal = $this->data;//$this->gateway->notification($this->data);

        if (!isset($aExternal['external_data'])) {
            $aExternal['external_data'] = [];
        }

        if(empty($aExternal['creator_id'])){
            $this->message = 'Необходим creator_id';
            return false;
        }
        
        $nUserId = $this->getUser($aExternal['creator_id']);
        $oBalance = (new Balance($aExternal['balance_id'],$nUserId));

        $bCheckBalance = $oBalance->check($aExternal['amount']);//$bCheckBalance = $oBalance->check($this->order->amount);
        Log::info('bCheckBalance = '.$bCheckBalance.' amount='.$aExternal['amount']);
        if (!$bCheckBalance) {
            $this->message = $oBalance->message;
            Log::info($this->message);
            return false;
        } else {
            $sPurpose = ($this->purpose) ? $this->purpose : array_get($this->aCancelTypes,$aExternal['type_id']);
            $aData = [
                'type_id' => $aExternal['type_id'],
                'balance_id' => $aExternal['balance_id'],
                'agent_id' => $aExternal['creator_id'],
                'amount' => $aExternal['amount'],
                'tariff_id' => $aExternal['tariff_id'],
                'packages' => array_get($aExternal,'packages',null),
                'listing_ads_id' => $aExternal['listing_ads_id'],
                'purpose' => $sPurpose,
            ];
            $aPayment = $oBalance->payment($aData, $aExternal['external_data']);
            Log::info('payment',$aPayment);
            if (!isset($aPayment['payment_id'])) {
                if (isset($aPayment['message'])) {
                    $this->message = $aPayment['message'];
                }
                Log::info('exit');
                return $aPayment['result'];
            }
            if (isset($aExternal['order_id'])) {
                $this->order = $this->db->find($aExternal['order_id']);
                $this->setPaymentId($aPayment['payment_id']);
                $this->setStatus(array_keys($this->db->statuses)[$aPayment['status']]);
                $this->setClosedAt();

            }
//            if ($aPayment['status'] === 2) {
//                $this->gateway->accept($aExternal['payment_id']);
//            }
//            dd($aPayment);
            return $aPayment['result'];
        }   
    }
    
    /*
     * Проверка при закрытии заказа
     * param array $aExternal = ['id'=>..,'amount'=>..]
     * return 
     */
    private function checkForClose($aExternal)
    {
        if (empty($this->order)) {
            return false;
        }
        if ($this->test) {
            if ($this->order->status !== 10) {
                $this->message = 'Статус Заказа не "'.$this->db->statuses[10].'".';
                return false;
            }
        } else {
            if ($this->order->status !== 1) {
                $this->message = 'Статус Заказа не "'.$this->db->statuses[1].'".';
                return false;
            }
        }

        if ($this->order->payment_id !== 0) {
            $this->message = 'Этому заказу уже присвоен платеж.';
            return false;
        }
        
        if (floatval($this->order->amount) !== floatval($aExternal['amount'])) {
            $this->message = 'Сумма заказа не совпадает с суммой уведомления.';
            return false;
        }

        if(!array_get($this->aCancelTypes,$aExternal['type_id'])){
            $this->message = 'Тип заказа не правильный.';
            return false;
        }

        if ($this->now > $this->order->expires_at) {
            $this->message = 'Заказ просрочен.';
            $this->setStatus(array_keys($this->db->statuses)[4]);
            return false;
        }

        $check = $this->gateway->check();
        return $this->returnMessage($check);
    }
    
    /*
     * Получение заказа
     * param integer $id
     * return object
     */
    public function get($id)
    {
        $this->order = $this->db->find($id);
        return $this->order;
    }
    
    /*
     * Получение баланса для заказа
     * param integer $id
     * return object
     */
    private function getBalance($id)
    {
        return BalanceMod::find($id);
    }
    
    /*
     * Отправка внутренних писем по заказу
     * param integer $id
     * return object
     */
    private function toMail()
    {
//        return Balance::find($id);
    }


    public function successUrl()
    {
        return $this->gateway->successUrl();
    }

    public function failUrl()
    {
        return $this->gateway->failUrl();
    }
    
    /*
     * Генерация кода
     * return string
     */
    private function generateCode() : string
    {
        return $this->order->update([
            'code' => $this->gateway->code().'-'.$this->invoice->id
        ]);
    }
    
    /*
     * Обновить статус
     * param integer $status
     * return bool
     */
    private function setStatus($status) : bool
    {
        if ($this->test) {
            $status = $status.'0';
        }
        return $this->order->update([
            'status' => $status
        ]);
    }

    /*
     * Обновить payment_id
     * param integer $payment_id
     * return bool
     */
    private function setPaymentId($paymentId) : bool
    {
        return $this->order->update(['payment_id'=>$paymentId]);
    }

    /*
     * Обновить дату закрытия платежа
     * return bool
     */
    private function setClosedAt() : bool
    {
        return $this->order->update(['closed_at'=>Carbon::now()]);
    }


    public function runTest() : void
    {
        $this->test = true;
    }


    private function returnMessage($aData)
    {
        if (isset($aData['message'])) {
            $this->message = $aData['message'];
        }
        return isset($aData['result']) ? $aData['result'] : false;
    }

    private function getUser($nCreatorId)
    {
        $oAgent = Agent::find($nCreatorId);
        return $oAgent->user_id;
    }
    
    public function getNotificationStatus()
    {
        return $this->notificationStatus;
    }
}