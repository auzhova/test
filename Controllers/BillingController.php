<?php

namespace App\Http\Controllers;

use App\Models\entity\Payment;
use App\Registries\Member as MemberRegistry;
use App\Services\Acquiring\Sberbank;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Http\Request;

/**
 * Контроллер для обработки оплаты.
 * Class BillingController
 * @package App\Http\Controllers
 */
class BillingController extends Controller
{
    /**
     * Создать платеж
     *
     * @param Request $request
     * @return Response
     */
    public function create(Request $request)
    {
        #Запрещаем доступ неавторизованным пользователям
        if($this->member->get('guest')){
            return $this->sendError('Forbidden', [], 403);
        }

        $input = $request->all();
        $validation = $this->validationCreate($input);

        if ($validation->fails()) {
            return $this->returnValidationMessages($validation);
        }

        $data = [
            'apiUri'   => Sberbank::API_URI_TEST,
        ];

        $client = new Sberbank($data);

        try {
            if(!isset($input['amount']) || empty($input['amount'])){
                $input['amount'] = ($input['type_id'] == 1) ? config('payment.amount_in_month') : config('payment.amount_in_month');
            }
            $oPayment = Payment::create(['user_id' => $input['user_id'], 'type_id' => $input['type_id'], 'amount' => $input['amount'], 'opened_at' => Carbon::now()]);
            $result = $client->register($oPayment->id,$input['amount'],route('billing.success'),['failUrl'=>route('billing.fail')]);
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(),['file' => $e->getFile(),'line' => $e->getLine()], 500);
        }

        if(!empty($result['errorCode'])){
            return $this->sendError($result['errorMessage']);
        }
        $oPayment->update(['order_id' => $result['orderId']]);
        return $this->sendResponse(['redirect' => $result['formUrl']]);
    }


    /**
     * Подтверждение платежа
     *
     * @param Request $request
     * @return Response
     */
    public function complete(Request $request)
    {
        #Запрещаем доступ неавторизованным пользователям
        if($this->member->get('guest')){
            return $this->sendError('Forbidden', [], 403);
        }

        $input = $request->all();
        $validation = $this->makeValidation($input, [
            'id' => 'required|integer|min:1'
        ], [], [
            'id' => 'Ид платежа',
        ]);

        if ($validation->fails()) {
            return $this->returnValidationMessages($validation);
        }

        $data = [
            'apiUri'   => Sberbank::API_URI_TEST,
        ];

        $oPayment = Payment::find($input['id']);
        if(!$oPayment){
            return $this->sendError('Платеж не найден.');
        }

        $client = new Sberbank($data);
        $result = $client->deposit($oPayment->order_id, $oPayment->amount);

        if(!empty($result['errorCode'])){
            return $this->sendError($result['errorMessage']);
        }
        return $this->sendResponse($result);
    }

    /**
     * Проверка статуса платежа
     *
     * @param Request $request
     * @return Response
     */
    public function check(Request $request)
    {
        #Запрещаем доступ неавторизованным пользователям
        if($this->member->get('guest')){
            return $this->sendError('Forbidden', [], 403);
        }

        $input = $request->all();
        $validation = $this->makeValidation($input, [
            'id' => 'required|integer|min:1'
        ], [], [
            'id' => 'Ид платежа',
        ]);

        if ($validation->fails()) {
            return $this->returnValidationMessages($validation);
        }

        $data = [
            'apiUri'   => Sberbank::API_URI_TEST,
        ];

        $oPayment = Payment::find($input['id']);
        if(!$oPayment){
            return $this->sendError('Платеж не найден.');
        }

        $client = new Sberbank($data);
        $result = $client->getOrderStatus($oPayment->order_id);

        if(!empty($result['errorCode'])){
            return $this->sendError($result['errorMessage']);
        }
        // возможно нужно будет обновить статус платежа
        return $this->sendResponse($result);
    }

    /**
     * Успешное завершения платежа
     *
     * @param Request $request
     * @return Response
     */
    public function success(Request $request)
    {
        #Запрещаем доступ неавторизованным пользователям
        if($this->member->get('guest')){
            return $this->sendError('Forbidden', [], 403);
        }

        $input = $request->all();
        $validation = $this->makeValidation($input, [
            'id' => 'required|integer|min:1'
        ], [], [
            'id' => 'Ид платежа',
        ]);

        if ($validation->fails()) {
            return $this->returnValidationMessages($validation);
        }

        $oPayment = Payment::find($input['id']);
        if(!$oPayment){
            return $this->sendError('Платеж не найден.');
        }

        $oPayment->update(['status' => Payment::DEPOSITED, 'closed_at' => Carbon::now()]);

        return $this->sendResponse(['Оплата прошла успешно.']);
    }

    /**
     * Неуспешное завершения платежа
     *
     * @param Request $request
     * @return Response
     */
    public function fail(Request $request)
    {
        #Запрещаем доступ неавторизованным пользователям
        if($this->member->get('guest')){
            return $this->sendError('Forbidden', [], 403);
        }

        $input = $request->all();
        $validation = $this->makeValidation($input, [
            'id' => 'required|integer|min:1'
        ], [], [
            'id' => 'Ид платежа',
        ]);

        if ($validation->fails()) {
            return $this->returnValidationMessages($validation);
        }

        $oPayment = Payment::find($input['id']);
        if(!$oPayment){
            return $this->sendError('Платеж не найден.');
        }

        $oPayment->update(['status' => Payment::DECLINED, 'closed_at' => Carbon::now()]);

        return $this->sendResponse(['Оплата прошла неуспешно.']);
    }


    /**
     * Отмены оплаты платежа
     *
     * @param Request $request
     * @return Response
     */
    public function reverse(Request $request)
    {
        #Запрещаем доступ неавторизованным пользователям
        if($this->member->get('guest')){
            return $this->sendError('Forbidden', [], 403);
        }

        $input = $request->all();
        $validation = $this->makeValidation($input, [
            'id' => 'required|integer|min:1'
        ], [], [
            'id' => 'Ид платежа',
        ]);

        if ($validation->fails()) {
            return $this->returnValidationMessages($validation);
        }

        $data = [
            'apiUri'   => Sberbank::API_URI_TEST,
        ];

        $oPayment = Payment::find($input['id']);
        if(!$oPayment){
            return $this->sendError('Платеж не найден.');
        }

        $client = new Sberbank($data);
        $result = $client->reverse($oPayment->order_id);

        if(!empty($result['errorCode'])){
            return $this->sendError($result['errorMessage']);
        }
        return $this->sendResponse($result);
    }

    /**
     * Возврат платежа
     *
     * @param Request $request
     * @return Response
     */
    public function refund(Request $request)
    {
        #Запрещаем доступ неавторизованным пользователям
        if($this->member->get('guest')){
            return $this->sendError('Forbidden', [], 403);
        }

        $input = $request->all();
        $validation = $this->makeValidation($input, [
            'id' => 'required|integer|min:1'
        ], [], [
            'id' => 'Ид платежа',
        ]);

        if ($validation->fails()) {
            return $this->returnValidationMessages($validation);
        }

        $data = [
            'apiUri'   => Sberbank::API_URI_TEST,
        ];

        $oPayment = Payment::find($input['id']);
        if(!$oPayment){
            return $this->sendError('Платеж не найден.');
        }

        $client = new Sberbank($data);
        $result = $client->refund($oPayment->order_id, $oPayment->amount);

        if(!empty($result['errorCode'])){
            return $this->sendError($result['errorMessage']);
        }
        return $this->sendResponse($result);
    }

    /**
     * Общие правила валидации
     *
     * @param $data
     * @return mixed
     */
    private function validationCreate(array $data)
    {
        $validation = $this->makeValidation($data, [
            'user_id' => 'required',
            'type_id' => 'required',
            //'amount'  => 'required',
        ], [], [
            'user_id' => 'Пользователь',
            'type_id' => 'Тип платежа',
            //'amount'  => 'Сумма',
        ]);
        return $validation;
    }
}
