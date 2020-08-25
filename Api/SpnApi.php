<?php
/**
 * Created by PhpStorm.
 * User: Nastya
 * Date: 29.01.2019
 * Time: 14:58
 */

namespace App\Services\Spn;

use App\Helper;
use Illuminate\Support\Facades\Log;
use Curl;

class SpnApi {
    
    public $answer = '';
    
    protected $sApiUrl = '';
    protected $sApiLogin = '';
    protected $sApiPassword = '';
    protected $sApiToken = '';


    public function __construct() {
        $this->sApiUrl = config('services.spn_api.url').'/api/v1';
        $this->sApiToken = config('services.spn_api.token');
        $this->sApiLogin = config('services.spn_api.login');
        $this->sApiPassword = config('services.spn_api.password');
    }

    /**
     * Метод используется для создания отдела и нового сотрудника с правами руководителя отдела в субагентской организации.
     * @param $aParams = [department_id - id отдела во внешней системе, user_id - id руководителя отдела во внешней системе, params - Oбъект необходимых данных для создания
     * params = [caption - Название организации, short_caption - Сокращенное название организации, register_date - Дата регистрации в формате "d.m.Y"(не обязатнльно)
     * address - Адрес(не обязательно), opf - Организационно правовая форма(не обязательно)
     * user_data - Объект данных руководителя организации
     * user_data = [surname - Фамилия руководителя организации, name - Имя руководителя организации, patronymic - Отчество руководителя организации,
     * phone - Телефон руководителя организации в формате "79998887766", email - Email руководителя организации]
     * @return bool
     */
    public function createDepartment(array $aParams) : bool {
        $sHost = $this->sApiUrl;
        $nId = Helper::Formating()->generateId();
        $aData['id'] = $nId;
        $aData['method'] = 'Integration.create_department';
        $aData['jsonrpc'] = '2.0';
        $aData['params'] = $aParams;
        $aAnswer = json_decode($this->curl($sHost, $aData), true);
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'result', [])){
            $bResult = true;
        }else{
            $bResult = false;
        }
        return $bResult;
    }

    /**
     * Метод используется для активации/деактивации отдела в системе spn24.ru.
     * @param $aParams = [department_id - id отдела во внешней системе, state - Флаг состояния активности (True or False)]
     * @return bool
     */
    public function departmentActivation(array $aParams) : bool {
        $sHost = $this->sApiUrl;
        $nId = Helper::Formating()->generateId();
        $aData['id'] = $nId;
        $aData['method'] = 'Integration.department_activation';
        $aData['jsonrpc'] = '2.0';
        $aData['params'] = $aParams;
        $aAnswer = json_decode($this->curl($sHost, $aData), true);
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'result', [])){
            $bResult = true;
        }else{
            $bResult = false;
        }
        return $bResult;
    }

    /**
     * Метод используется для активации/деактивации сотрудника отдела в системе spn24.ru.
     * @param $aParams = [department_id - id отдела во внешней системе, user_id - id сотрудника отдела во внешней системе, state - Флаг состояния активности (True or False)]
     * @return bool
     */
    public function userActivation(array $aParams) : bool {
        $sHost = $this->sApiUrl;
        $nId = Helper::Formating()->generateId();
        $aData['id'] = $nId;
        $aData['method'] = 'Integration.user_activation';
        $aData['jsonrpc'] = '2.0';
        $aData['params'] = $aParams;
        $aAnswer = json_decode($this->curl($sHost, $aData), true);
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'result', [])){
            $bResult = true;
        }else{
            $bResult = false;
        }
        return $bResult;
    }

    /**
     * Метод используется для изменения руководителя отдела.
     * @param $aParams = [department_id - id отдела во внешней системе, user_id - id сотрудника отдела во внешней системе]
     * @return bool
     */
    public function changeBoss(array $aParams) : bool {
        $sHost = $this->sApiUrl;
        $nId = Helper::Formating()->generateId();
        $aData['id'] = $nId;
        $aData['method'] = 'Integration.change_boss';
        $aData['jsonrpc'] = '2.0';
        $aData['params'] = $aParams;
        $aAnswer = json_decode($this->curl($sHost, $aData), true);
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'result', [])){
            $bResult = true;
        }else{
            $bResult = false;
        }
        return $bResult;
    }

    /**
     * Метод используется для получения ссылки на приложение для интеграции, а так же для создания пользователя и обновления данных уже созданного пользователя.
     * @param $aParams = [department_id - id отдела во внешней системе, user_id - id сотрудника отдела во внешней системе,
     * user_data - объект необходимых полей для создания и обновления данных пользователя(surname,name,patronymic,phone,email)]
     * @return bool
     */
    public function getReferrer(array $aParams) : bool {
        $sHost = $this->sApiUrl;
        $nId = Helper::Formating()->generateId();
        $aData['id'] = $nId;
        $aData['method'] = 'Integration.get_referrer';
        $aData['jsonrpc'] = '2.0';
        $aData['params'] = $aParams;
        Log::info('Spn24 send:',$aData);
        $aAnswer = json_decode($this->curl($sHost, $aData), true);
        Log::info('Spn24 getReferrer:',$aAnswer);
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'result', [])){
            $bResult = true;
        }else{
            $bResult = false;
        }
        return $bResult;
    }

    /**
     * Метод используется для проверки статуса отдела или пользователя.
     * @param $aParams = [id - id отдела или пользователя во внешней системе, entity - тип передаваемого id ('department' или 'user')
     * @return bool
     */
    public function checkActive(array $aParams) : bool {
        $sHost = $this->sApiUrl;
        $nId = Helper::Formating()->generateId();
        $aData['id'] = $nId;
        $aData['method'] = 'Integration.check_active';
        $aData['jsonrpc'] = '2.0';
        $aData['params'] = $aParams;
        $aAnswer = json_decode($this->curl($sHost, $aData), true);
        $this->answer = $aAnswer;
        $result = array_get($aAnswer,'result', '');
        if($result){
            if(strpos($result, 'not exist') !== false){
                $bResult = false;
            }else{
                $bResult = true;
            }
        }else{
            $bResult = false;
        }
        return $bResult;
    }

    /**
     * Собираем запрос
     * @param string $host
     * @param array|null $data
     * @param string $method
     * @param string $path
     * @param array|null $headers
     * @return string
     */
    public function curl(string $host, array $data=[], string $method = 'POST', array $headers = [], string $path =''): string {
        $content = (is_array($data) && !empty($data)) ? json_encode($data) : $data;
        $aHeaders = [
            'Content-Type: application/json',
            'Authorization: WWWINTEGRATION '.$this->sApiToken,
        ];
        if(!empty($headers)){
            $aHeaders = $headers;
        }
        $request = Curl::to($host)->withData($content)
            ->withHeaders($aHeaders);
        switch ($method){
            case 'GET':
                $answer = $request->get();
                break;
            case 'POST':
                $answer = $request->post();
                break;
            case 'PUT':
                $answer = $request->put();
                break;
            case 'DOWNLOAD':
                $answer = $request->download($path);
                break;
        }

        if((bool) $answer === false){
            $this->error = 'error post request';
            return '';
        }
        return $answer;
    }


    public function getErrors(): Collection{
        return (is_array($this->errors)) ? collect($this->errors) : collect([$this->errors]);
    }
    
    public function parseError($data){
        $aErrors = [
            'Error. Departament already exist.'=>'Отдел уже существует.',
            'Error. User already exist' => 'Пользователь уже существует',
            'Error. Phone already exist.' => 'Телефон уже существует.',
            'Error. Departament not exist.' => 'Отдела не существует.',
            'Error. User not exist.' => 'Пользователь не существует.',
            'Error. Departament or user not exist.' => 'Отдел или пользователь не существует.',
            'Error. Departament not active.' => 'Отдел не активен.',
            'Error. User not exist or not active.' => 'Ошибка. Пользователь не существует или не активен.',
        ];
        if(isset($data['error'])){
            $message = array_get($data, 'error.data.clear_message');
            if($message){
                return array_get($aErrors, $message,'Неизвестная ошибка.');
            }else{
                return 'Неизвестная ошибка.';
            }
        }
    }
}