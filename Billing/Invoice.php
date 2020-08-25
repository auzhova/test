<?php

namespace App\Services\Billing;

use App\Models\Agent;
use App\Models\PaymentsInvoice;
use App\Models\Balance as BalanceMod;
use App\Services\Billing\Gates\BillingPayGateInterface;
use Carbon\Carbon;

class Invoice implements BillingInterface
{
    private $db = null;
    /**
     * Модель Invoice текущий счет
     *
     * @var
     */
    private $invoice;

    /**
     * Чистичная оплата
     *
     * @var bool
     */
    private $partial_payment = false;

    /**
     * Текущий шлюз
     *
     * @var
     */
    private $gateway;

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
     * Менятся только через @see setMessage()
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
     * Статус уведомлений после неуспешной проверки
     *
     * @var int
     */
    protected $notificationStatus = 400;


    public function __construct(BillingPayGateInterface $gateway)
    {
        $this->log = new BillingLogger();

        $this->gateway = $gateway; //  присвоить определенный Gate
        $this->db = new PaymentsInvoice;

        $this->now = Carbon::now();

        $this->config['min_pay'] = floatval(config('billing.' . $this->gateway->key . '.min_pay'));
        $this->config['max_pay'] = floatval(config('billing.' . $this->gateway->key . '.max_pay'));

        $this->config['expires'] = config('billing.' . $this->gateway->key . '.expires');
    }


    /*
     * Открыть счет
     * param 
     * return 
     */
    public function open()
    {
        $this->before();

        $this->log->info(__FUNCTION__);

        if (!$this->checkForOpen()) {
            return $this->error();
        }

        $this->invoice = $this->create($this->data);

        $this->logInvoice();

        $this->generateCode();

        $this->setStatus(array_keys($this->db->statuses)[1]);

        $result = $this->gateway
            ->setContacts($this->getContacts())
            ->invoice($this->invoice)
            ->url();

        if (!$result['result']) {
            $this->setMessage($result['message']);
            if (isset($result['exception'])) {
                $this->log->info($result['message']);
                $this->setUserableMessage();
            }
            $this->setStatus(array_keys($this->db->statuses)[3]);
        }
        if (isset($result['external_id']) && !empty($result['external_id'])) {
            $this->setExternalId($result['external_id']);
        }
        if (isset($result['url'])) {
            $this->log->info('Invoice success');
            $this->logInvoice();
            $this->log->finish();
            return $result['url'];
        } else {
            return $this->error();
        }
    }


    /**
     * Достать контакты из плательщика
     * - email
     * - phone
     *
     * @return array
     */
    private function getContacts()
    {
        $contacts = [];
        $balance = $this->invoice->balance;

        if ($balance->type === 'agency') {
            $oContactModel = $balance->agency;
            if (!is_null($oContactModel)) {
                $phones = json_decode($oContactModel->phone, true);
                $phone = '';
                if (!empty($phones)) {
                    $phone = array_shift($phones);
                    $phone = preg_replace("/[^0-9]/",'', $phone);
                }
                $contacts['email'] = $oContactModel->url.'@'.config('app.maildomain');
                $contacts['phone'] = $phone;
            }
        } elseif ($balance->type === 'user') {
            $oContactModel = $balance->agent;

            if (!is_null($oContactModel)) {
                $phones = json_decode($oContactModel->phone, true);
                $phone = null;
                if (is_null($phone) && isset($phones['work'])) {
                    $phone = $phones['work'];
                }
                if (is_null($phone) && isset($phones['mob'])) {
                    $phone = $phones['mob'];
                }
                if (is_null($phone) && isset($phones['self'])) {
                    $phone = $phones['self'];
                }
                if (is_null($phone)) {
                    $oAgency = $oContactModel->agency;
                    if (is_null($oAgency)) {
                        $agencyPhones = json_decode($oAgency->phone, true);
                        $phone = isset($agencyPhones['work']) ? $agencyPhones['work'] : array_shift($agencyPhones);
                    }
                }
                if (empty($phone) && !empty($phones)) {
                    $phone = array_shift($phones);
                }
                $phone = preg_replace("/[^0-9]/",'', $phone);
                $contacts['email'] = !empty($oContactModel->login) ? $oContactModel->login : $oContactModel->user->email;
                $contacts['phone'] = $phone;
            }
        } else {
            return $contacts;
        }
        $this->log->info('contacts', $contacts);
        return $contacts;
    }

    /**
     * Скрипты до выполнения методов, например
     * - включить режим теста в шлюзах
     */
    private function before(): void
    {
        if ($this->test) {
            //$this->log->runTest();

            $this->log->start();

            $this->log->test();

            $this->gateway->runTest();

        } else {

            $this->log->runProduction();

            $this->log->start();

            $this->log->production();
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
    public function data(array $data): void
    {
        $this->data = $data;
    }

    /**
     * Получить данные из $this->data
     *
     * @return array
     */
    public function getData() : array
    {
        return $this->data;
    }

    /*
     * Проверка при открытие счета
     * param 
     * return array ['result' => true|false, 'message' => '']
     */
    private function checkForOpen()
    {
        if (!$this->checkAmount()) {
            return false;
        }
        $check = $this->gateway->check();
        return $this->returnMessage($check);
    }

    /**
     * Проверка amount на минимальную и максимальную сумму
     *
     * @return bool
     */
    private function checkAmount()
    {
        if ($this->data['amount'] < $this->config['min_pay']) {
            $this->setMessage('Сумма платежа должна быть больше или равна ' . number_format($this->config['min_pay'], 2, '.', ' ').' руб.');
            return false;
        }
        if ($this->data['amount'] > $this->config['max_pay']) {
            $this->setMessage('Сумма платежа должна быть меньше или равна ' . number_format($this->config['max_pay'], 2, '.', ' ').' руб.');
            return false;
        }
        return true;
    }
    
    /*
     * Создать счет
     * param array $data
     * return object
     */
    private function create($data) : PaymentsInvoice
    {
        $data['payment_id'] = 0;
        $data['gateway'] = $this->gateway->key;
        $data['status'] = array_keys($this->db->statuses)[0];//array_keys((new PaymentsInvoice())->statuses)[0];
        $data['opened_at'] = $this->now;
        $data['expires_at'] = $this->now->copy()->addDays($this->config['expires']);

        $this->log->info('create Invoice');

        return $this->db->create($data);
    }
        
    /*
     * Отправка данных на шлюз и запроса
     * param integer $id
     * 
     */
    private function gatewaySend($id) 
    {
        // $this->gateway->data($this->invoice)->send() : array external data
        // отправка данных на шлюз и запроса, try catch в data для проверки данных для отправка
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

        $aExternal = $this->gateway->notification($this->data);

        $result = $this->returnMessage($aExternal);
        if (!$result) {
            return $this->error();
        }

        $this->invoice = $this->db->find($aExternal['invoice_id']);

        $this->gateway->invoice($this->invoice);

        if (!$this->checkForClose($aExternal)) {
            return $this->error();
        }

        if (!isset($aExternal['external_data'])) {
            $aExternal['external_data'] = [];
        }

        $result = $this->closeByBalance($aExternal);

        return $result ? $this->success() : $this->error();
    }

    private function closeByBalance($aExternal)
    {
        $nUserId = $this->getUser();
        $oBalance = (new Balance($this->invoice->balance_id,$nUserId));

        $amount = floatval($aExternal['amount_without_commission']);

        $this->log->info('Amount: '.floatval($aExternal['amount']).', Amount without commission: '.$amount);

        $bCheckBalance = (in_array($aExternal['type_id'],[1,2])) ? $oBalance->check(): $oBalance->check($amount);
        if (!$bCheckBalance) {
            $this->setMessage($oBalance->message);
            return false;
        } else {
            $sPurpose = ($this->purpose) ? $this->purpose : array_get($this->aRepTypes,$aExternal['type_id']).' по счету №'.$this->invoice->code;
            if ($this->test) {
                $sPurpose = 'test';
                $oBalance->runTest();
            }
            $aData = [
                'type_id' => $aExternal['type_id'],
                'balance_id' => $this->invoice->balance_id,
                'agent_id' => $this->invoice->creator_id,
                'amount' => $amount,
                'purpose' => $sPurpose,
            ];
            $aPayment = $oBalance->payment($aData, $aExternal['external_data']);
            if (!isset($aPayment['payment_id'])) {
                if (isset($aPayment['message'])) {
                    $this->setMessage($aPayment['message']);
                }
                return $aPayment['result'];
            }

            $this->setPaymentId($aPayment['payment_id']);
            $this->setStatus(array_keys($this->db->statuses)[$aPayment['status']]);
            $this->setClosedAt();

            if ($aPayment['status'] === 2) {
                $this->gateway->accept($aExternal['payment_id']);
            }
            return $aPayment['result'];
        }
    }
    
    /*
     * Проверка при закрытии счета
     * param array $aExternal = ['id'=>..,'amount'=>..]
     * return 
     */
    private function checkForClose($aExternal)
    {
        if (is_null($this->invoice)) {
            $this->setMessage('Счет №'.$aExternal['invoice_id'].' не найден.');
            return false;
        }

        if ($this->invoice->gateway !== $this->gateway->key) {
            $this->setMessage('Способ закрытия не соответствует типу '.$this->invoice->gateway.'.');
            return false;
        }

        if ($this->test) {
            if ($this->invoice->status !== 10) {
                $this->setMessage('Статус Счета не "'.$this->db->statuses[10].'".');
                return false;
            }
        } else {
            if ($this->invoice->status !== 1) {
                $this->setMessage('Статус Счета не "'.$this->db->statuses[1].'".');
                return false;
            }
        }

        if ($this->invoice->payment_id !== 0) {
            $this->setMessage('Этому счету уже присвоен платеж.');
            return false;
        }
        
        if (floatval($this->invoice->amount) !== floatval($aExternal['amount'])) {
            $this->setMessage('Сумма счета не совпадает с суммой уведомления.');
            $this->setStatus(array_keys($this->db->statuses)[3]);
            $this->setClosedAt();
            return false;
        }

        if(!array_get($this->aRepTypes,$aExternal['type_id'])){
            $this->setMessage('Тип счета не правильный.');
            return false;
        }

        if (!is_null($this->invoice->expires_at)) {
            if ($this->now > $this->invoice->expires_at) {
                $this->setMessage('Счет просрочен.');
                $this->setStatus(array_keys($this->db->statuses)[5]);
                $this->setClosedAt();
                return false;
            }
        }

        if (!is_null($this->invoice->closed_at)) {
            $this->setMessage('У счета есть дата закрытия.');
            return false;
        }

        $check = $this->gateway->check();
        return $this->returnMessage($check);
    }
    
    /*
     * Получение счета
     * param integer $id
     * return object
     */
    public function get($id)
    {
        $this->invoice = $this->db->find($id);
        return $this->invoice;
    }
    
    /*
     * Получение баланса для счета
     * param integer $id
     * return object
     */
    private function getBalance($id)
    {
        return BalanceMod::find($id);
    }
    
    /*
     * Отправка внутренних писем по счету
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
        return $this->invoice->update([
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
        $this->log->info('set status '.$status);
        return $this->invoice->update([
            'status' => $status
        ]);
    }

    /**
     * Сохранить в счет внешний id платежа
     *
     * @param $id
     * @return bool
     */
    private function setExternalId($id) : bool
    {
        return $this->invoice->update([
            'external_id' => $id
        ]);
    }

    /*
     * Обновить payment_id
     * param integer $payment_id
     * return bool
     */
    private function setPaymentId($paymentId) : bool
    {
        return $this->invoice->update(['payment_id'=>$paymentId]);
    }

    /*
     * Обновить дату закрытия платежа
     * return bool
     */
    private function setClosedAt() : bool
    {
        return $this->invoice->update(['closed_at'=>Carbon::now()]);
    }


    public function runTest() : void
    {
        $this->test = true;
    }


    private function returnMessage($aData)
    {
        if (isset($aData['message'])) {
            $this->setMessage($aData['message']);
            if (isset($aData['exception'])) {
                $this->log->info($aData['message']);
                $this->setUserableMessage();
            }
            if (isset($aData['status'])) {
                $this->log->info($aData['status']);
                $this->notificationStatus = $aData['status'];
            }
        }
        return isset($aData['result']) ? $aData['result'] : false;
    }

    private function getUser()
    {
        $oAgent = Agent::find($this->invoice->creator_id);
        return $oAgent->user_id;
    }

    /**
     * Выполняется перед выходном из класса
     * Назначение:
     * - записывать в log почему мы вышли
     * - логировать счет
     *
     * @return bool
     */
    private function error() : bool
    {
        $this->log->info($this->message);
        $this->logInvoice();
        $this->log->finish();
        return false;
    }

    private function success() : bool
    {
        $this->log->info(__FUNCTION__);
        $this->logInvoice();
        $this->log->finish();
        return true;
    }

    /**
     * Присвоение сообщения
     * Назначение:
     * - записывать в log сообщение
     *
     * @param $message
     */
    private function setMessage($message) : void
    {
        $this->message = $message;
    }

    /**
     * Логирование счета со всеми параметрами
     */
    private function logInvoice() : void
    {
        if (!is_null($this->invoice)) {
            $this->log->info('Invoice ID: '.$this->invoice->id, $this->invoice->toArray());
        }
    }

    /**
     * Получить счет
     *
     * @param $id
     * @return bool
     */
    public function externalGet($id) : bool
    {
        $this->before();

        $this->log->info(__FUNCTION__);


        $this->invoice = $this->findExternal($id);

        if (is_null($this->invoice)) {
            $this->setMessage('Счет с external_id: '.$id.' не найден.');
            return $this->error();
        }

        $this->logInvoice();

        $this->data = [
            'invoice' => $this->invoice->toArray()
        ];
        return $this->success();
    }

    /**
     * Подтвердить/закрыть счет
     *
     * @param $id
     * @return bool
     */
    public function externalAccept($id) : bool
    {
        $this->before();

        $this->log->info(__FUNCTION__);

        $this->invoice = $this->findExternal($id);

        if (is_null($this->invoice)) {
            $this->setMessage('Счет с external_id: '.$id.' не найден.');
            return $this->error();
        }

        $this->logInvoice();

        $this->gateway->invoice($this->invoice);

        $aExternal = [
            'payment_id' => $this->invoice->external_id,
            'type_id' => 1,
            'external_data' => [],
            'amount' => $this->invoice->amount
        ];

        if (!$this->checkForClose($aExternal)) {

            //$this->gateway->refund($aExternal['payment_id']);
            return $this->error();
        }
        if (!isset($aExternal['external_data'])) {
            $aExternal['external_data'] = [];
        }

        $result = $this->closeByBalance($aExternal);

        return $result ? $this->success() : $this->error();
    }

    /**
     * Отменить счет
     *
     * @param $id
     * @return bool
     */
    public function externalCancel($id) : bool
    {
        $this->before();

        $this->log->info(__FUNCTION__);

        $this->invoice = $this->findExternal($id);

        if (is_null($this->invoice)) {
            $this->setMessage('Счет с external_id: '.$id.' не найден.');
            return $this->error();
        }
        $this->logInvoice();

        $this->gateway->invoice($this->invoice);

        $aExternal = [
            'payment_id' => $this->invoice->external_id,
            'type_id' => 1,
            'external_data' => [],
            'amount' => $this->invoice->amount
        ];

        if (!$this->checkForClose($aExternal)) {
            return $this->error();
        }

        $result = $this->gateway->cancel($id);

        $result = $this->returnMessage($result);
        if (!$result) {
            return $this->error();
        }
        $this->setStatus(array_keys($this->db->statuses)[4]);

        $this->setClosedAt();

        return $this->success();
    }

    private function setUserableMessage()
    {
        $this->setMessage('В данный момент оплата этим способом не возможна. Попробуйте позже.');
    }

    public function getNotificationStatus()
    {
        return $this->notificationStatus;
    }

    /**
     * Найти счет по external_id
     *
     * @param $id
     * @return mixed
     */
    private function findExternal($id)
    {
        return $this->db->where('external_id', $id)->first();
    }



}