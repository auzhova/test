<?php
/**
 * Created by PhpStorm.
 * User: Nastya
 * Date: 30.03.2017
 * Time: 12:13
 */

namespace App\Services\Tinkoff;

use App\Helper;
use Illuminate\Support\Facades\Log;
use Curl;

class TinkoffApi
{
    
    public $answer = '';
    
    protected $sApiUrl = '';
    protected $sApiKey = '';
    
    public $aDictionaries =[
        'creditProgram' => 'credit_program_types',
        'creditMarket' => 'credit_market_types',
        'mortgagePledgeType' => 'realty_types',
        'mortgageEvidenceToSupportRating' => 'form_income_extended_types',
        'formIncome' => 'form_income_extended_types'
    ];


    public function __construct() {
        $this->sApiUrl = config('services.api_tinkoff.url');//https://www-qa.tcsbank.ru/api/mortgage тестовый сервис
        $this->sApiKey = config('services.api_tinkoff.key'); //da82c22b-c10c-4e44-8201-246cfbeb991d CRMSecretKey
    }
    
    /**
     * Метод для регистрации специалиста в Тинькофф
     * @param $aData ['apiKey' => 15808, 'fio' => 'Дмитрий Иванов', 'phone' => '71113542287','email'=>'jjjjjjj@mail.ru','inn'=>772022312160];
     * apiKey - id from contacts table
     * @return bool
     */
    public function register(array $aData) : bool {
        $sHost = $this->sApiUrl.'/crm/register';
        $aData['crmSecretKey'] = $this->sApiKey;
        $aAnswer = json_decode($this->send($sHost, $aData), true);
        Log::info('----------- Answer register -----------',$aAnswer ?? []);
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'responseCode', '') == 200){
            $bResult = true;
        }else{
            $bResult = false;
            Log::info('answer : Error!');
        }
        return $bResult;
    }
    
    /**
     * Обновление контактной информации по специалисту
     * @param $aData ['apiKey' => 15808, 'fio' => 'Дмитрий Иванов', 'phone' => '71113542287','email'=>'jjjjjjj@mail.ru','inn'=>772022312160];
     * apiKey - id from contacts table
     * @return bool
     */
    public function updateAgentInfo(array $aData) : bool {
        $sHost = $this->sApiUrl.'/crm/update_agent_info';
        $aData['crmSecretKey'] = $this->sApiKey;
        $aAnswer = json_decode($this->send($sHost, $aData), true);
        Log::info('----------- Answer updateAgentInfo -----------',$aAnswer);
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'responseCode', '') == 200){
            $bResult = true;
        }else{
            $bResult = false;
            Log::info('answer : Error!');
        }
        return $bResult;
    }
    
    /**
     * Удаление учетной записи специалиста
     * @param $aData ['apiKey' => 15808]
     * @return bool
     */
    public function deleteAgentInfo(array $aData) : bool {
        $sHost = $this->sApiUrl.'/crm/delete_agent';
        $aData['crmSecretKey'] = $this->sApiKey;
        $aAnswer = json_decode($this->send($sHost, $aData), true);
        Log::info('----------- Answer deleteAgentInfo -----------',$aAnswer);
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'responseCode', '') == 200){
            $bResult = true;
        }else{
            $bResult = false;
            Log::info('answer : Error!');
        }
        return $bResult;
    }
    
    /**
     * Получение списка id всех заявок специалиста
     * @param $aData ['apiKey' => 15808]
     * @return bool
     */
    public function getApplications(array $aData) : bool {
        $sHost = $this->sApiUrl.'/crm/applications';
        $aData['crmSecretKey'] = $this->sApiKey;
//        $aAnswer = json_decode($this->send($sHost, $aData), true);
        $return = $this->curl($sHost, $aData);
        $aAnswer = json_decode($return, true);
        if($aAnswer) {
            Log::info('----------- Answer getApplications -----------', $aAnswer);
        } else {
            Log::error('Error: method=getApplications, response from API: '. $return);
            $aAnswer = [
                'api_error' => true,
                'message'   => 'Ошибка связи с серверами Тинькофф. Попробуйте повторить попытку позже.'
            ];
        }
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'responseCode', '') == 200){
            $this->answer = array_get($aAnswer,'data', []);
            $bResult = true;
        }else{
            $bResult = false;
            Log::info('answer : Error!');
        }
        return $bResult;
    }

    /**
     * Метод расчета предложений банков // Калькулятор
     * @param $aData = [
            "creditProgram" => "Покупка квартиры",//Название кредитной программы*
            "creditMarket" => "Новостройка", //Рынок кредитования*
            "mothershipMoney" => false,
            "region" =>  "г. Москва",//Название города
            "cladr" => "string",//КЛАДР-код города
            "readyToPay" => 15000,//Сколько готов платить в месяц
            "creditAmount" => 5000000,// Примерная сумма кредита
            "cost" => 1000000, //Примерная стоимость залога
            "firstPayment" => 47000,//Сумма первичного платежа
            "realEstateId" => 0, //Идентификатор объекта в базе тинькофф
            "income" => 35000,//Ежемесячный доход
            "approximateExpenses" => 5500,// Расходы по кредитам участников
            "term" => 13,//Срок кредита в месяцах
            "contacts" => [
                  "contactType" => "Основной",//Тип клиента. Строка, принимающая значения Основной или Дополнительный
                  "formIncome" => "Найм, Справка 2-НДФЛ",//Форма подтверждения дохода
                  "citizenship" => "РФ"//Гражданство
            ],
            "notCountIncome" => true,//Флаг оптимальности дохода. При значении true по этому параметру не будут отсекаться банки(true/false)
        ];
     *  $aData = [
            "creditProgram" => "Покупка квартиры",
            "creditMarket" => "Новостройка",
            "mothershipMoney" => false,
            "cost" => 2000000,
            "creditAmount" => 1100000,
            "region" => "г. Москва",
            "income" => 100000,
            "term" => 180,
            "contacts" => [
                [
                    "contactType" => "Основной",
                    "formIncome" => "Найм, Справка 2-НДФЛ",
                    "citizenship" => "РФ"
                ]
            ] ,

        ];
     * @return bool
     */
    public function banksDecisions(array $aData) : bool {
        $sHost = $this->sApiUrl.'/crm/banks_decisions';
        $aData['crmSecretKey'] = $this->sApiKey;
//        $aAnswer = json_decode($this->send($sHost, $aData), true);
        $return = $this->curl($sHost, $aData);
        $aAnswer = json_decode($return, true);
        if($aAnswer) {
            Log::info('----------- Answer banksDecisions -----------', $aAnswer);
        } else {
            Log::error('Error: method=banksDecisions, response from API: '. $return);
            $aAnswer = [
                'api_error' => true,
                'message'   => 'Ошибка связи с серверами Тинькофф. Попробуйте повторить попытку позже.'
            ];
        }
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'responseCode', '') == 200){
            $this->answer = array_get($aAnswer,'data', []);
            $bResult = true;
        }else{
            $bResult = false;
            Log::info('answer : Error!');
        }
        return $bResult;
    }
    
    /**
     * Создание заявки клиента в системе Тинькофф
     * @param $aData $aData = ['apiKey' => 15];
        $aData['application'] = [//для подачи заявки
            "firstName" => "Иван", //Имя*
            "lastName" => "Семенов", //Фамилия*
            "middleName" => "Иванович", //Отчество*
            "mobilePhone" => "84579876541", //Строка, состоящая из 11 цифр. Первая цифра 8.*
            "email" => "sssss@mail.ru", //@
            "creditAmount" => "1000000", //Примерная сумма кредита, >= 0///ВСЕ СТРОКИ, даже Цифры/Суммы
            "firstPayment" => "200000", //Примерный первоначальный взнос
            "cost" => "5000000", //Примерная стоимость залога*, проверка cost = creditAmount + firstPayment  
            "readyToPay" => "10000", // Сколько готов платить в месяц*
            "creditProgram" => "Покупка квартиры", // Цель ипотеки*
            "creditMarket" => "Новостройка", // Рынок кредитования*
            "region" => "г. Москва", // Регион приобретения*
            "cladr" => "string", // КЛАДР-код региона приобретения
            "mortgagePledgeType" => "string", //Тип недвижимости, оставляемой в залог
            "mortgageMothershipMoney" => false, //Наличие материнского капитала
            "mortgageTerm" => 0, //Срок кредита в годах
            "nonResidentNationality" => "РФ", //Гражданство
            "mortgageEvidenceToSupportRating" => "string", // Форма подтверждения дохода
            "birthDate" => "2000-01-30", // Дата рождения. Возраст должен быть от 18 до 74 лет
            "onlyPledge" =>  true, // что это и для чего?
            "passport" => [//Паспортные данные*
                "division" => "132-541", // Код подразделения
                "issueDate" => "2018-02-25", // Дата выдачи паспорта
                "issuer" => "ОТДЕЛОМ УФМС РОССИИ ПО РЕСПУБЛИКЕ ТАТАРСТАН В НОВО-САВИНОВСКОМ Р-НЕ Г. КАЗАНИ", // Кем выдан паспорт
                "birthPlace" => "Республика Татарстан, Казань", // Место рождения
                "serieNumber" => "9217 248289" //Номер паспорта
            ],
            "registrationAddress" => [
                "plainAddress" => "Москва,Новочерёмушкинская улица, 71/32, подъезд 1", // Полный адрес
                "cladr" => "string", // КЛАДР
                "houseNum" => "string", // Номер дома
                "block" => "string", // Корпус дома
                "building" => "string", // Строение
                "flatNum" => "string" // Номер квартиры
                ],
            "livingAddress" => [
                "plainAddress" => "string", // Полный адрес
                "cladr" => "string", // КЛАДР
                "houseNum" => "string", // Номер дома
                "block" => "string", // Корпус дома
                "building" => "string", // Строение
                "flatNum" => "string" // Номер квартиры
            ]
        ];
     * @return bool
     */
    public function createСlient(array $aData) : bool {
        $sHost = $this->sApiUrl.'/crm/create_client';
        $aData['crmSecretKey'] = $this->sApiKey;
//        $aAnswer = json_decode($this->send($sHost, $aData), true);
        $return = $this->curl($sHost, $aData);
        $aAnswer = json_decode($return,true);
        if($aAnswer) {
            Log::info('----------- Answer createСlient -----------', $aAnswer);
        } else {
            Log::error('Error: method=createClient, response from API: '. $return);
            $aAnswer = [
                'api_error' => true,
                'message'   => 'Ошибка связи с серверами Тинькофф. Попробуйте повторить попытку позже.'
            ];
        }
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'responseCode', '') == 200){
            $this->answer = array_get($aAnswer,'data', []);
            $bResult = true;
        }else{
            $bResult = false;
            Log::error('answer : Error!');
        }
        return $bResult;
    }
    
    /**
     * Получение информации по заявке, включая необходимые доработки от банка
     * @param $aData ['apiKey' => 15808,'applicationId' => "ce0f3f87470d2df2226bd7949c192939"]
     * @return bool
     */
    public function clientInfo(array $aData) : bool {
        $sHost = $this->sApiUrl.'/crm/client_info';
        $aData['crmSecretKey'] = $this->sApiKey;
//        $aAnswer = json_decode($this->send($sHost, $aData), true);
        $return = $this->curl($sHost, $aData);
        $aAnswer = json_decode($return, true);
        if($aAnswer) {
            Log::info('----------- Answer clientInfo -----------',$aAnswer);
        } else {
            Log::error('Error: method=clientInfo, response from API: '. $return);
            $aAnswer = [
                'api_error' => true,
                'message'   => 'Ошибка связи с серверами Тинькофф. Попробуйте повторить попытку позже.'
            ];if(array_key_exists('api_error', $aAnswer) && $aAnswer['api_error']) {
                $aAnswer['message'] = 'Ошибка связи с серверами Тинькофф. Попробуйте повторить попытку позже.';
            }
        }
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'responseCode', '') == 200){
            $this->answer = array_get($aAnswer,'data', []);
            $bResult = true;
        }else{
            $bResult = false;
            Log::info('answer : Error!');
        }
        return $bResult;
    }
    
    /**
     * Метод отказа от ипотечной заявки
     * @param $aData ['apiKey' => 15808,'applicationId' => "ce0f3f87470d2df2226bd7949c192939"]
     * @return bool
     */
    public function rejectApplication(array $aData) : bool {
        $sHost = $this->sApiUrl.'/crm/reject_application';
        $aData['crmSecretKey'] = $this->sApiKey;
//        $aAnswer = json_decode($this->send($sHost, $aData), true);
        $aAnswer = json_decode($this->curl($sHost, $aData), true);
        Log::info('----------- Answer rejectApplication -----------',$aAnswer);
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'responseCode', '') == 200){
            $this->answer = array_get($aAnswer,'data', []);
            $bResult = true;
        }else{
            $bResult = false;
            Log::info('answer : Error!');
        }
        return $bResult;
    }
    
    /**
     * Получение сессии для работы в ЛК Тинькофф
     * @param $aData ['apiKey' => 15808,'applicationId' => "ce0f3f87470d2df2226bd7949c192939"]
     * @return bool
     */
    public function getSession(array $aData) : bool {
        $sHost = $this->sApiUrl.'/crm/session';
        $aData['crmSecretKey'] = $this->sApiKey;
//        $aAnswer = json_decode($this->send($sHost, $aData), true);
        $return = $this->curl($sHost, $aData);
        $aAnswer = json_decode($return, true);
        if($aAnswer) {
            Log::info('----------- Answer getSession -----------', $aAnswer);
        } else {
            Log::error('Error: method=getSession, response from API: '. $return);
            $aAnswer = [
                'api_error' => true,
                'message'   => 'Ошибка связи с сервисом Тинькофф. Обратитесь в техподдержку.'
            ];
        }
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'responseCode', '') == 200){
            $this->answer = array_get($aAnswer,'data', []);
            $bResult = true;
        }else{
            $bResult = false;
            Log::error('answer : Error!');
        }
        return $bResult;
    }
    
    /**
     * Авторизация в ЛК Тинькофф под вопросом редирект????
     * @param string $sSession //сессия
     * @return string
     */
    public function authTinkoff(string $sSession) : string {
        return 'https://www.tinkoff.ru/ipoteka/external-auth/?sessionid='.$sSession;
    }
    
    /**
     * Метод отказа от отправки в банк
     * @param $aData ['apiKey' => 15808,'applicationId' => "ce0f3f87470d2df2226bd7949c192939","code" => "GPB"] Код банка
     * @return bool
     */
    public function rejectBank(array $aData) : bool {
        $sHost = $this->sApiUrl.'/crm/reject_bank';
        $aData['crmSecretKey'] = $this->sApiKey;
//        $aAnswer = json_decode($this->send($sHost, $aData), true);
        $aAnswer = json_decode($this->curl($sHost, $aData), true);
        Log::info('----------- Answer rejectBank -----------',$aAnswer);
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'responseCode', '') == 200){
            $this->answer = array_get($aAnswer,'data', []);
            $bResult = true;
        }else{
            $bResult = false;
            Log::info('answer : Error!');
        }
        return $bResult;
    }
    
    /**
     * Метод восстановления банка
     * @param $aData ['apiKey' => 15808,'applicationId' => "ce0f3f87470d2df2226bd7949c192939","code" => "GPB"] Код банка
     * @return bool
     */
    public function restoreBank(array $aData) : bool {
        $sHost = $this->sApiUrl.'/crm/restore_bank';
        $aData['crmSecretKey'] = $this->sApiKey;
//        $aAnswer = json_decode($this->send($sHost, $aData), true);
        $aAnswer = json_decode($this->curl($sHost, $aData), true);
        Log::info('----------- Answer restoreBank -----------',$aAnswer);
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'responseCode', '') == 200){
            $this->answer = array_get($aAnswer,'data', []);
            $bResult = true;
        }else{
            $bResult = false;
            Log::info('answer : Error!');
        }
        return $bResult;
    }
    
    /**
     * Обновление текущего статуса клиента по Сберу в системе Тинькофф
     * @param $aData ['apiKey' => 15808,'applicationId' => "ce0f3f87470d2df2226bd7949c192939","bankCode" => "SBER", "status" => "PLANNED_SEND"]
     * @return bool
     */
    public function updateBankStatus(array $aData) : bool {
        $sHost = $this->sApiUrl.'/crm/update_bank_status';
        $aData['crmSecretKey'] = $this->sApiKey;
//        $aAnswer = json_decode($this->send($sHost, $aData), true);
        $aAnswer = json_decode($this->curl($sHost, $aData), true);
        Log::info('----------- Answer updateBankStatus -----------',$aAnswer);
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'responseCode', '') == 200){
            $this->answer = array_get($aAnswer,'data', []);
            $bResult = true;
        }else{
            $bResult = false;
            Log::info('answer : Error!');
        }
        return $bResult;
    }

    /**
     * Получение справочника для параметра
     * @param string $sDictionary = creditProgram||creditMarket||mortgagePledgeType||mortgageEvidenceToSupportRating||formIncome
     * @return bool
     */
    public function getDictionary(string $sDictionary) : bool {
//        $sHost = $this->sApiUrl.'/dictionaries/'.$this->aDictionaries[$sDictionary];
        $sHost = $this->sApiUrl.'/dictionaries/'.$sDictionary;
        $aData = [];
        $aAnswer = json_decode($this->send($sHost, $aData, "GET"), true);
        Log::info('----------- Answer getDictionary -----------',$aAnswer);
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'responseCode', '') == 200){
            $this->answer = array_get($aAnswer,'data', []);
            $bResult = true;
        }else{
            $bResult = false;
            Log::info('answer : Error!');
        }
        return $bResult;
    }
    
    /**
     * Получение валидации региона приобретения
     * @param string $sRegion = 'Москва'
     *  city - регион, который необходимо передать на вход методу создания заявки
        available - флаг доступности ипотеки для данного региона
     * @return bool
     */
    public function getAvailableRegions(string $sRegion) : bool {
        $sHost = 'https://api.tinkoff.ru/mortgage/calc/available_regions?region='.$sRegion;
        $aData = [];
        $aAnswer = json_decode($this->send($sHost, $aData, "GET"), true);
        Log::info('----------- Answer getAvailableRegions -----------',$aAnswer);
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'responseCode', '') == 200){
            $this->answer = array_get($aAnswer,'data', []);
            $bResult = true;
        }else{
            $bResult = false;
            Log::info('answer : Error!');
        }
        return $bResult;
    }
    
    
    /**
     * Получение регионы приобретения
     * @return bool
     */
    public function getRegions() : bool {
        $sHost = 'https://api.tinkoff.ru/mortgage/calc/available_regions?region=';
        $aData = [];
        $aAnswer = json_decode($this->send($sHost, $aData, "GET"), true);
        Log::info('----------- Answer getRegions -----------',$aAnswer);
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'responseCode', '') == 200){
            $this->answer = array_get($aAnswer,'data', []);
            $bResult = true;
        }else{
            $bResult = false;
            Log::info('answer : Error!');
        }
        return $bResult;
    }
    
    /**
     * Получение валидации гражданства
     * @return bool
     */
    public function getResidentNationality() : bool {
        $sHost = 'https://api.tinkoff.ru/mortgage/util/citizenship';
        $aData = [];
        $aAnswer = json_decode($this->send($sHost, $aData, "GET"), true);
        Log::info('----------- Answer getResidentNationality-----------',$aAnswer);
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'responseCode', '') == 200){
            $this->answer = array_get($aAnswer,'data', []);
            $bResult = true;
        }else{
            $bResult = false;
            Log::info('answer : Error!');
        }
        return $bResult;
    }
    
    
    /**
     * Собираем запрос, прицепляем к нему $token и отправляем его в банк, возвращаем ответ от банка
     * @param string $host
     * @param array|null $data
     * @param string $method
     * @return string
     */
    public function send(string $host, array $data=[], string $method = 'POST'): string {
        $aHeaders = [
            'Content-Type: application/json; charset=UTF-8'
        ];
        if(count($data)){
            $content = json_encode($data);
        }else{
            $content = '';
        }
        Log::info($content);
//        dd($content);
        switch ($method){
            case 'GET':
                $answer = Helper::Transfers()->get($host, $content, $aHeaders);
                break;
            case 'POST':
                $answer = Helper::Transfers()->post($host, $content, $aHeaders);
                break;
            case 'FILE':
                $answer = Helper::Transfers()->file($host, $data, $aHeaders);
        }

        if((bool) $answer === false){
            $this->error = 'error post request';
            return '';
        }
        return $answer;
    }
    
    
    /**
     * Собираем запрос
     * @param string $host
     * @param array|null $data
     * @param string $method
     * @param string $path
     * @return string
     */
    public function curl(string $host, array $data=[], string $method = 'POST', string $path =''): string {
        $content = count($data) ? json_encode($data) : $data;
        $request = Curl::to($host)->withData($content)//->appendDataToURL()
                ->withContentType('application/json');
//                ->withHeaders([
//                    'Content-Type: application/json; charset=UTF-8'
//                ]);
        switch ($method){
            case 'GET':
                $answer = $request->get();
                break;
            case 'POST':
                $answer = $request->post();
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
    
    public function parseError($Errors){
        if(isset($Errors['message'])){
            return $Errors['message'];
        }
        if(isset($Errors['data'])){
            return array_values($Errors['data']);
        }
    }
}