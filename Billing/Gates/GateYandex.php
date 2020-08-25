<?php

namespace App\Services\Billing\Gates;


use App\Http\Requests\Request;
use YandexCheckout\Client;
use YandexCheckout\Common\Exceptions\BadApiRequestException;

class GateYandex extends BillingGate implements BillingPayGateInterface
{
    public $key = 'yandex';

    private $client = null;

    private $id;

    private $token;

    private $enabled;

    private $testCard = [
        'number' => '1111 1111 1111 1026',
        'month' => 'Больше текущей даты',
        'cvc' => [
            000, // 3d-secure отключен
            123  // 3d-secure включен
        ]
    ];

    private $commissions = [];

    public function __construct()
    {
        $this->id = config('services.billing.yandex.id');
        $this->token = config('services.billing.yandex.token');
        $this->enabled = config('services.billing.yandex.enabled');

        $this->commissions = config('billing.yandex.commission');

        $this->client = new Client();

    }

    public function check() : array
    {
        if ($this->test) {
            return $this->success();
        }
        if (!$this->enabled) {
            return $this->error('Сервис временно недоступен.');
        }
        if ($this->id === '') {
            return $this->error('ID магазина не найден.');
        }
        if ($this->token === '') {
            return $this->error('TOKEN магазина не найден.');
        }
        return $this->success();
    }
    
    public function notification($aData) : array
    {
        if ($this->checkNotification($aData)) {
            $aObject = $aData['object'];
            $amount = $aObject['amount']['value'];
            $paymentMethod = $aObject['payment_method']['type'];

            return $this->success([
                'invoice_id' => $aObject['metadata']['invoice_id'],
                'amount' => $amount,
                'amount_without_commission' => $this->amountWithoutCommission($amount, $this->commission($paymentMethod)),
                'type_id' => 1,
                'payment_id' => $aObject['id'],
                'external_data' => $aData
            ]);
        } else {
            if (isset($aData['object']['status']) && $aData['object']['status'] === 'succeeded') {
                return $this->error('Платеж имеет статус "succeeded".', [], 200);
            }
            return $this->error('Уведомление не прошло проверку.');
        }
    }

    private function checkNotification($aData)
    {
        $aObject = $aData['object'];

        if (!isset($aObject['status'])) {
            return false;
        }
        if ($aObject['status'] !== 'waiting_for_capture') {
            return false;
        }
        if (!$aObject['paid']) { // Признак оплаты заказа
            return false;
        }
        return true;
    }

    public function failUrl() : string
    {
        return url('/invoice/yandex/fail');
    }

    public function successUrl() : string
    {
        $i = md5($this->invoice->id.$this->invoice->amount);
        $url = url('/balance/p/view-invoice/?i=');
        return $url.$i;
    }

    /**
     * Код платежного шлюза
     *
     * @return string
     */
    public function code() : string
    {
        return 'ЯК';
    }

    /**
     * Получить url - где платить
     *
     * @return array
     */
    public function url() : array
    {
        try {
            $data = [
                'amount' => [
                    'value' => floatval($this->invoice->amount),
                    'currency' => 'RUB'
                ],
                'description' => 'Оплата счета №'.$this->invoice->id,
                'confirmation' => [
                    'type' => 'redirect',
                    'return_url' => config('app.url').'/balance', // ?
                ],
                'receipt' => [
                    'items' => [
                        [
                            'description' => 'Оплата счета №'.$this->invoice->id,
                            'quantity' => '1',
                            'amount' => [
                                'value' => floatval($this->invoice->amount),
                                'currency' => 'RUB'
                            ],
                            // Ставка НДС. Значение 4 = 18%(20% с 01.01.2019) НДС. Было 1 = Без НДС
                            'vat_code' => 4
                        ]
                    ],
                    'email' => array_get($this->contacts, 'email', ''),
                    'phone' => array_get($this->contacts, 'phone', ''),
                ],
                /*
                'recipient' => [
                    'gateway_id' => $this->invoice->id,
                    'account_id' => $this->id
                ],
                */
                'metadata' => [
                    'invoice_id' => $this->invoice->id
                ]
            ];
            if (!is_null($this->invoice->title)) {
                $data['description'] = $this->invoice->title;
            }
            if (isset($data['receipt']['email']) && !empty($data['receipt']['email']) &&
                isset($data['receipt']['phone']) && !empty($data['receipt']['phone'])
            ) {
                $data['receipt']['phone'] = '';
            }
            /*
            $data['payment_method_data'] = [
                'type' => 'bank_card'
            ];
            */
            if ($this->test) {
                $payment = $this->client()->createPayment($data);
                $url = array_get($payment, 'confirmation.confirmation_url', '');
                $external_id = array_get($payment, 'id', '');
                return $this->success([
                    'url' => $url,
                    'external_id' => $external_id,
                ]);
            } else {
                $payment = $this->client()->createPayment($data);
                info('------------ '.__FUNCTION__.' ------------');
                info(json_encode($payment));
                info('------------ '.__FUNCTION__.' ------------');
                $url = array_get($payment, 'confirmation.confirmation_url', '');
                $external_id = array_get($payment, 'id', '');
                return $this->success([
                    'url' => $url,
                    'external_id' => $external_id,
                ]);
            }
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), [
                'exception' => true
            ], __FUNCTION__);
        }
    }

    /**
     * Отменить платеж, сделать возврат
     *
     * @param null $paymentId
     * @return array
     */
    public function cancel($paymentId = null) : array
    {
        try {
            if ($this->test) {
                $response = json_encode(['status' => 'canceled']);
            } else {
                $this->client->setAuth($this->id, $this->token);
                $response = $this->client()->cancelPayment($paymentId);
            }
            $data = $response;
            //$data = json_decode($response, true);
            if (!$this->test && isset($data['test'])) {
                return $this->error('При отмене вернулся test при не тестовой операции.');
            }
            if ($data['status'] === 'canceled') {
                return $this->success();
            } else {
                return $this->error('Платеж не имеет статус "canceled".');
            }
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), [
                'exception' => true
            ], __FUNCTION__);
        }
    }

    /**
     * Подтвердить платеж
     *
     * @param null $paymentId
     * @return array
     */
    public function accept($paymentId = null) : array
    {
        try {
            if ($this->test) {
                $response = json_encode(['status' => 'succeeded']);
            } else {
                $response = $this->client()->capturePayment([
                    'amount' => [
                        'value' => floatval($this->invoice->amount),
                        'currency' => 'RUB',
                    ],
                ], $paymentId);
            }
            $data = $response;
            info('------------ '.__FUNCTION__.' ------------');
            info(json_encode($data));
            info('------------ '.__FUNCTION__.' ------------');
            //$data = json_decode($response, true);
            if (!$this->test && isset($data['test'])) {
                return $this->error('При подтвержении вернулся test при не тестовой операции.');
            }
            if ($data['status'] === 'succeeded') {
                return $this->success();
            } else {
                return $this->error('Платеж не имеет статус "succeeded".');
            }
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), [
                'exception' => true
            ], __FUNCTION__);
        }
    }

    /**
     * Авторизоваться клиентом с учетом теста
     *
     * @return Client
     */
    private function client()
    {
        return !$this->test ?
            $this->client->setAuth($this->id, $this->token) :
            $this->client->setAuth($this->id, $this->token);
            //$this->client->setAuth($this->id, 'test_'.$this->token);
    }

    /**
     * Комиссия по типу платежа
     *
     * @param $method 'bank_card'
     * @return float 3.5
     */
    private function commission($method)
    {
        if (isset($this->commissions[$method])) {
            return (float) $this->commissions[$method];
        } else {
            return isset($this->commissions['default']) ?
                (float) $this->commissions['default'] :
                0.0;
        }
    }

    /**
     * Комиссия по сумме
     *
     * @param $amount 1 000
     * @param $commission 3.5
     * @return float|int 35.0
     */
    private function commissionByAmount($amount, $commission)
    {
        return $commission !== 0.0 ? (floatval($amount) * ($commission / 100)) : 0.0;
    }

    /**
     * Сумма без комиссии
     *
     * @param $amount 1 000
     * @param $commission 3.5
     * @return float|int 965.0
     */
    private function amountWithoutCommission($amount, $commission)
    {
        return $commission !== 0.0 ?
            floatval($amount) - $this->commissionByAmount($amount, $commission) :
            floatval($amount);
    }


}