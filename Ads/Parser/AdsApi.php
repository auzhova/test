<?php
/**
 * Created by PhpStorm.
 * User: Nastya
 * Date: 18.12.2018
 * Time: 15:57
 */

namespace App\Services\Parser;

use App\Services\Parser\Gates\ParserInparsGateInterface;
use Carbon\Carbon;
use Ixudra\Curl\Facades\Curl;

class AdsApi
{

    public $answer = '';

    protected $sApiUrl = '';
    protected $sApiKey = '';
    protected $sApiToken = '';
    protected $sApiLogin = '';
    protected $sApiPassword = '';
    protected $sAuthBasic = '';

    public function __construct() {
        $this->sApiUrl = env('API_URL', 'http://ads-api.ru/main');
        $this->sApiLogin = env('API_LOGIN', '');
        $this->sApiToken = env('API_TOKEN', 'c7909250803252f04baf7e635011b920');
        $this->sApiPassword= env('API_PASSPORT', 'xcAso9fSg6');
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
        $data['user'] = $this->sApiLogin;
        $data['token'] = $this->sApiToken;
        $host = $host.'?'.http_build_query($data);
        $content = (is_array($data) && !empty($data)) ? json_encode($data) : $data;
        /*
        HTTP-заголовок Authorization: Basic с base64-кодированным значением username:password.
        В качестве имени пользователя (username) используется токен доступа к API, пароль оставьте пустым, но символ : так же должен быть закодирован.
        */
        $aHeaders = [
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($this->sApiToken . ':')
        ];
        if(!empty($headers)){
            $aHeaders = $headers;
        }
        $request = Curl::to($host)//->withData($content)
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

    /**
     * Список объявлений
     * @param array $params
     * category_id	ID категории, из которой будут получаться объявления. Таблицу со списком категорий смотрите ниже. Для сокращения числа запросов можно сразу запрашивать несколько категорий через запятую, например: category_id=2,3
     * q	Текст для поиска по названию.
     * price1	Цена от.
     * price2	Цена до.
     * date1	Дата от. Пример "2014-11-02" или "2014-11-02 17:10:00". Время московское и отстает на 30 минут от текущего. Смотрите пример на php.
     * date2	Дата до. Пример "2014-11-02" или "2014-11-02 17:10:00". Время московское и отстает на 30 минут от текущего. Смотрите пример на php.
     * person_type	Тип автора объявления. 1-Частное лицо (как указано на источнике), 2-Агентство, 3-Собственник (наложен фильтр по количеству объявлений с одним телефоном в нашей базе, если <5 объявлений, то считается, что собственник).
     * city	 Название города или области или региона. Для сокращения числа запросов можно сразу задать несколько значений через символ |. Например city=Москва|Тула.
     * metro	Название метро или района для фильтрации. Если в городе есть метро, то надо указывать обязательно метро, а не район.
     * nedvigimost_type	Тип недвижимости для категории "Недвижимость" и подкатегорий. Возможные значения смотрите ниже. Для сокращения числа запросов можно сразу запрашивать несколько значений через запятую, например: nedvigimost_type=1,2
     * phone	Телефон, в формате 8xxxYYYYYYY, по которому требуется фильтрация. Выводит объявления только с этим телефоном.
     * source	Сайт-источник: 1 - avito.ru, 2 - irr.ru, 3 - realty.yandex.ru, 4 - cian.ru, 5 - sob.ru, 6 - youla.io, 7 - n1.ru, 8 - egent.ru, 9 - mirkvartir.ru, 10 - moyareklama.ru. Можно задать сразу несколько значение через запятую, например, так: source=1,7
     * param[xxx]	Дополнительные параметры, вместо xxx должен стоять код параметра. Таких дополнительных параметров может быть несколько. Чтобы узнать код xxx и значение параметра зайдите на сайт и сделайте выборку, задав в фильтре требуемые дополнительные параметры, в адресной строке можно будет увидеть, например, param[1943]=Продам.
        Чтобы в фильтре увидеть дополнительные параметры, необходимо выбрать категорию второго уровня (на белом фоне), дополнительные параметры будут в нижней строке.
     * format	Формат возвращаемых данных. Может принимать значения: json или xml. По-умолчанию json.
     * phone_operator	нет	Оператор сотовой связи. Доступные значения параметра смотрите ниже.
     * limit	Ограничивает количество объявлений в выборке. Не может быть больше 1000 для платного доступа, и больше 50 для тестового доступа.
     * startid	При задании этого параметра, запрос возвращает набор объявлений, значение id которых больше или равно значению заданного параметра. Значения в наборе отсортированы по id по возрастанию.
     Если задан этот параметр, параметры date1 и date2 не учитываются. С помощью этого параметра можно получать порции новых объявлений, не задавая временной интервал, достаточно задать id последнего полученного объявления плюс 1.
     * withcoords	Если параметр равен 1, то возвращаются только объявления с координатами, отличными от 0, т.е. если есть координаты. Потому как, например, у объявлений с egent нет координат.
     * @return bool
     */
    public function listObjects(array $params = []) : bool {
        $sHost = $this->sApiUrl.'/api';
        if(!isset($params['category_id'])){
            $params['category_id'] = '2,3,4,5,6,7';
        }
        $aAnswer = json_decode($this->curl($sHost, $params), true);
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'code') == 200){
            $this->answer = array_get($aAnswer,'data',[]);
            $bResult = true;
        }else{
            $bResult = false;
        }
        return $bResult;
    }

    /**
     * Получение объявления по id
     * @param array $params ['id'=>1213]
     * id	integer	Идентификатор объявления.
     * Формат возвращаемых данных. Может принимать значения: json или xml. По-умолчанию json.
     * @return bool
     */
    public function objectId(array $params) : bool {
        $sHost = $this->sApiUrl.'/apigetone';
        $aAnswer = json_decode($this->curl($sHost, $params), true);
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'code') == 200){
            $this->answer = array_get($aAnswer,'data',[]);
            $bResult = true;
        }else{
            $bResult = false;
        }
        return $bResult;
    }

    /**
     * Список регионов
     * @param string $format
     * @return bool
     */
    public function listRegions(string $format = 'json') : bool {
        $sHost = $this->sApiUrl.'/v1/region';
        dd($this->curl($sHost, ['_format' => $format]));
        $aAnswer = json_decode($this->curl($sHost, ['_format' => $format]), true);
        //Log::info('----------- Answer SmartDealApi checkNumber -----------',$aAnswer);
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'objects', []) == true){
            $bResult = true;
        }else{
            $bResult = false;
            Log::info('----------- SmartDealApi -----------');
            Log::info('answer checkNumber: Error!');
        }
        return $bResult;
    }

    /**
     * Список городов
     * @param integer $regionId
     * @param string $format
     * @return bool
     */
    public function listCities(integer $regionId, string $format = 'json') : bool {
        $sHost = $this->sApiUrl.'/v1/city';
        $aData = [
            'regionId' => $regionId,
            '_format' => $format
        ];
        dd($this->curl($sHost, $aData));
        $aAnswer = json_decode($this->curl($sHost, $aData), true);
        //Log::info('----------- Answer SmartDealApi checkNumber -----------',$aAnswer);
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'objects', []) == true){
            $bResult = true;
        }else{
            $bResult = false;
            Log::info('----------- SmartDealApi -----------');
            Log::info('answer checkNumber: Error!');
        }
        return $bResult;
    }

    /**
     * Список разделов
     * @param string $format
     * @return bool
     */
    public function listSections(string $format = 'json') : bool {
        $sHost = $this->sApiUrl.'/v1/estate/section';
        $aData = [
            '_format' => $format
        ];
        dd($this->curl($sHost, $aData));
        $aAnswer = json_decode($this->curl($sHost, $aData), true);
        //Log::info('----------- Answer SmartDealApi checkNumber -----------',$aAnswer);
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'objects', []) == true){
            $bResult = true;
        }else{
            $bResult = false;
        }
        return $bResult;
    }

    /**
     * Список категорий
     * @param string $format
     * @return bool
     */
    public function listCategories(integer $sectionId, string $format = 'json') : bool {
        $sHost = $this->sApiUrl.'/v1/category?access-token='.$this->sApiToken;
        $aData = [
            'sectionId' => $sectionId,
            '_format' => $format
        ];
        dd($this->curl($sHost, $aData,'GET',['Accept: application/json']));
        $aAnswer = json_decode($this->curl($sHost, $aData,'GET',['Accept: application/json']), true);
        //Log::info('----------- Answer SmartDealApi checkNumber -----------',$aAnswer);
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'objects', []) == true){
            $bResult = true;
        }else{
            $bResult = false;
        }
        return $bResult;
    }

    /**
     * Список активных подписок
     * @param string $format
     * @return bool
     */
    public function listSubscribes(string $format = 'json') : bool {
        $sHost = $this->sApiUrl.'/v1/user/subscribe?access-token='.$this->sApiToken;
        $aData = [
            '_format' => $format
        ];
        dd($this->curl($sHost, $aData,'GET',['Accept: application/json']));
        $aAnswer = json_decode($this->curl($sHost, $aData,'GET',['Accept: application/json']), true);
        //Log::info('----------- Answer SmartDealApi checkNumber -----------',$aAnswer);
        $this->answer = $aAnswer;
        if(array_get($aAnswer,'objects', []) == true){
            $bResult = true;
        }else{
            $bResult = false;
        }
        return $bResult;
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