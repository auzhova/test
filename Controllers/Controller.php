<?php

namespace App\Http\Controllers;

use App\Registries\Member;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Validator;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * @var Member Реестр с данными по текущему пользователю
     */
    protected $member;

    /**
     * Create a new controller instance.
     * @param $member Member
     * @return void
     */
    public function __construct(Member $member)
    {
        $this->member = $member;
    }

    /**
     * success response method.
     *
     * @return \Illuminate\Http\Response
     */
    public function sendResponse($result, $message)
    {
        $response = [
            'success' => true,
            'data'    => $result,
            'message' => $message,
        ];
        return response()->json($response, 200);
    }

    /**
     * return error response.
     *
     * @return \Illuminate\Http\Response
     */
    public function sendError($error, $errorMessages = [], $code = 404)
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];
        if(!empty($errorMessages)){
            $response['data'] = $errorMessages;
        }
        return response()->json($response, $code);
    }

    /**
     * Вызов Валидатора
     *
     * @param array $data
     * @param array $rules
     * @param array $messages
     * @param array $customAttributes
     * @return mixed
     */
    protected function makeValidation(array $data, array $rules, array $messages = [], array $customAttributes = [])
    {
        return Validator::make($data, $rules, $messages, $customAttributes);
    }

    /**
     * Трансформация сообщений валидации
     *
     * @param $validation
     * @return \Illuminate\Http\Response
     */
    protected function returnValidationMessages($validation)
    {
        $messages = $validation->getMessageBag()->toArray();
        $messages = array_shift($messages);
        return $this->sendError(isset($messages[0]) ? $messages[0] : 'Ошибка валидации', $messages, 422);
    }
}
