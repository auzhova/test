<?php
/**
 * Created by PhpStorm.
 * User: Nastya
 * Date: 18.12.2018
 * Time: 17:43
 */

namespace App\Services\Parser;

use App\Models\RefBooksValue;
use App\Services\Parser\ParserAdsInterface;
use Carbon\Carbon;
use App\Services\Parser\dsApi;

class AdsParser implements ParserAdsInterface
{
    /**
     * Категории
     * @var array
     */
    private $category = [
            1 => 'Недвижимость',
            2 => 'Квартиры',
            3 => 'Комнаты',
            4 => 'Дома, дачи, коттеджи',
            5 => 'Земельные участки',
            6 => 'Гаражи и машиноместа',
            7 => 'Коммерческая недвижимость',
            8 => 'Недвижимость за рубежом'
    ];

    /**
     * Тип объявления, сопоставить со справочниками
     * @var array
     */
    private $nedvigimost_type_id = [
        4 => 'Продам',
        5 => 'Сдам',
        6 => 'Куплю',
        7 => 'Сниму'
    ];

    private $category_land = [
        157 => 'Поселений (ИЖС)',
        164 => 'Сельхозназначения (СНТ, ДНП)',
        160 => 'Промназначения'
    ];

    private $class_builging = ['A','B','C','D'];

    private $cat7 = [
        132 => 'Гостиница',
        112 => 'Офисное помещение',
        125 => 'Помещение свободного назначения',
        117 => 'Производственное помещение',
        52 => 'Складское помещение',
        62 => 'Торговое помещение'
    ];

    private $person_type = [
        1 => 1,'Частное лицо/Собственник',
        2 => 2,'Агентство/Компания'
    ];

    private $matching = [
        "id" => "ads.id",
        "url" => "ads.url",
        "title" => "ads.title",
        "price" => "price.value",
        "time" => 'ads.published_at',
        "phone" => "contact.phone",
        "person" => "contact.name",
        "person_type_id" => "contact.person_type",
        "contactname" => "contact.contactname",
        "count_ads_same_phone" => "contact.count_ads",
        "address" => "geo.address",
        "description" => "ads.description",
        "source_id" => "params.source",
        "cat2_id" => "params.realty_type",
        "coords" => "ads.coords",
        "region" => "geo.region",
        "city1" => "geo.city",
        "images" => "images",
        //"params" => "params",
    ];

    private $mapping = [
        'Тип объявления' => ['name' => 'deal_type', 'rb_id' => 3],
        'Количество комнат' => ['name' => 'number_of_rooms', 'rb_id' => 0],
        'Вид объекта' => ['name' => 'realty_subtype', 'rb_id' => 2],//исключения для Вторички/Новостройки - квартиры
        'Площадь' => ['name' => 'all_area', 'rb_id' => 0],
        'Площадь дома' => ['name' => 'all_area', 'rb_id' => 0],
        'Этаж' => ['name' => 'floor', 'rb_id' => 0],
        'Этажей в доме' => ['name' => 'number_of_floors', 'rb_id' => 0],
        'Этажность здания' => ['name' => 'number_of_floors', 'rb_id' => 0],
        'Площадь кухни' => ['name' => 'kitchen_area', 'rb_id' => 0],
        'Жилая площадь' => ['name' => 'living_area', 'rb_id' => 0],
        'Площадь комнаты' => ['name' => 'living_area', 'rb_id' => 0],
        'Срок сдачи' => ['name' => 'lease_term', 'rb_id' => 5],
        'Срок аренды' => ['name' => 'lease_term', 'rb_id' => 5],
        'Тип дома' => ['name' => 'building_type', 'rb_id' => 11],
        'Материал стен' => ['name' => 'building_type', 'rb_id' => 11],
        'Тип гаража' => ['name' => 'building_type', 'rb_id' => 11],
        'Адрес' => ['name' => 'address', 'rb_id' => 0],
        'Комиссия' => ['name' => 'commission', 'rb_id' => 20],
        'Размер комиссии' => ['name' => 'commission_size', 'rb_id' => 0],
        'Залог' =>  ['name' => 'deposit_month', 'rb_id' => 0],
        'Комнат в квартире' => ['name' => 'rooms_for_deal', 'rb_id' => 0],
        'Расстояние до города' => ['name' => 'city_distance', 'rb_id' => 0],
        'Площадь участка' => ['name' => 'plottage', 'rb_id' => 0],
        'Категория земель' => ['name' => 'land_category', 'rb_id' => 6],
        'Тип машиноместа' => ['name' => 'type_carplace', 'rb_id' => 18],
        //'Охрана' => ['name' => 'guarding', 'rb_id' => 18],
        //'Класс здания' => 'building_class',
    ];

    private $building_type = [
        26 => 'Кирпичный',
        31 => 'Панельный',
        31 => 'Блочный',
        29 => 'Монолитный',
        38 => 'Деревянный',
        36 => 'Металлический',
        36 => 'Железобетонный',
    ];

    private $building_wall = [
        26 => 'Кирпич',
        40 => 'Брус',
        41 => 'Бревно',
        36 => 'Металл',
        43 => 'Пеноблоки',
        44 => 'Сэндвич-панели',
        45 => 'Ж/б панели',
        46 => 'Экспериментальные материалы'
    ];

    private $sources = [
        1 => 273,//'avito.ru',
        2 => 274,//'irr.ru',
        3 => 275,//'realty.yandex.ru',
        4 => 276,//'cian.ru',
        5 => 277,//'sob.ru',
        6 => 278,//'youla.io',
        7 => 279,//'n1.ru',
        8 => 280,//'egent.ru',
        9 => 282,//'mirkvartir.ru',
        10 => 282,//'moyareklama.ru',
    ];

    private $mappling2 = [
        'deal_type' => [ //Тип объявления
            'param_1943', //Квартиры
            'param_2517', //Комнаты
            'param_3040', //Дома, дачи, коттеджи
            'param_4195', //Земельные участки
            'param_4780', //Гаражи и машиноместа
            'param_4867', //Коммерческая недвижимость
            'param_4924', //Недвижимость за рубежом
        ],
        'number_of_rooms' => [ //Количество комнат
            'param_1945',
            'param_2019',
            'param_2085',
        ],
        'object_type' => [//вид объекта
            'param_1957', //Вторичка/Новостройка - категория 2 (продам)
            'param_3042', //Дом/Дача/Коттедж/Таунхаус - категория 4 (продам)
            'param_3428', //Дом/Дача/Коттедж/Таунхаус - категория 4 (сдам)
            'param_3817', //Дом/Дача/Коттедж/Таунхаус - категория 4 (куплю)
            'param_3826', //Дом/Дача/Коттедж/Таунхаус - категория 4 (сниму)
            'param_4782', //Гараж(продам)
            'param_4798', //Гараж(сдам)
            'param_4814', //Гараж/Машиноместо(куплю)
            'param_4818', //Гараж/Машиноместо(сниму)
            'param_4869', //Гостиница/Офисное - категория 7 (продам)
            'param_4887', //Гостиница/Офисное - категория 7 (сдам)
            'param_4905', //категория 7 куплю
            'param_4913', //категория 7 сниму
        ],
        'area' => [
            'param_2313', //2 - продам
            'param_2515', //2 - сдам
            'param_4014', //4 - продам
            'param_4193', //4 - сдам
            'param_4616', //5 - продам
            'param_4779', //5 - сдам
            'param_4821', //6 - продам
        ],
        'living_area' => [
            'param_12722', //2 - продам
            'param_12724', //2 - сдам
        ],
        'kitchen_area' => [
            'param_12721', //2 - продам
            'param_12723', //2 - сдам

        ],
        'address' => [
            'param_2314', //2 - продам
            'param_2516', //2 - сдам
            'param_2837', //3 - продам
            'param_3039', //3 - сдам
            'param_7424', //4 - продам
            'param_7425', //4 - сдам
            'param_7442', //5 - продам
            'param_7443', //5 - сдам
        ],
        'room_area' => [
            'param_2836', //3 - куплю
            'param_3038', //3 - сдам
        ],
        'plottage' => [//Площадь участка
            'param_4015', //4 - куплю
            'param_4194', //4 - сдам
        ],

        3 => 'Комнаты',
        4 => 'Дома, дачи, коттеджи',
        5 => 'Земельные участки',
        6 => 'Гаражи и машиноместа',
        7 => 'Коммерческая недвижимость',
        8 => 'Недвижимость за рубежом',
    ];

    /**
     * Ключи параметров поиска
     * @var array
     */
    private $keyParams = [
        'category_id',//	нет	ID категории, из которой будут получаться объявления. Таблицу со списком категорий смотрите ниже. Для сокращения числа запросов можно сразу запрашивать несколько категорий через запятую, например: category_id=2,3
        'q',//	нет	Текст для поиска по названию.
        'price1',//	нет	Цена от.
        'price2',//	нет	Цена до.
        'date1',//	нет	Дата от. Пример "2014-11-02" или "2014-11-02 17:10:00". Время московское и отстает на 30 минут от текущего. Смотрите пример на php.
        'date2',//	нет	Дата до. Пример "2014-11-02" или "2014-11-02 17:10:00". Время московское и отстает на 30 минут от текущего. Смотрите пример на php.
        'person_type',//	нет	Тип автора объявления. 1-Частное лицо (как указано на источнике), 2-Агентство, 3-Собственник (наложен фильтр по количеству объявлений с одним телефоном в нашей базе, если <5 объявлений, то считается, что собственник).
        'city',//	нет	Название города или области или региона. Для сокращения числа запросов можно сразу задать несколько значений через символ |. Например city=Москва|Тула. Названия регионов, используемые в системе, смотрите ниже.
        'metro',//	нет	Название метро или района для фильтрации. Если в городе есть метро, то надо указывать обязательно метро, а не район.
        'nedvigimost_type',//	нет	Тип недвижимости для категории "Недвижимость" и подкатегорий. Возможные значения смотрите ниже. Для сокращения числа запросов можно сразу запрашивать несколько значений через запятую, например: nedvigimost_type=1,2
        'phone',//	нет	Телефон, в формате 8xxxYYYYYYY, по которому требуется фильтрация. Выводит объявления только с этим телефоном. Внимание! При тестовом доступе этот параметр запрещён, и запрос с этим параметром будет отклонён.
        'source',//	нет	Сайт-источник: 1 - avito.ru, 2 - irr.ru, 3 - realty.yandex.ru, 4 - cian.ru, 5 - sob.ru, 6 - youla.io, 7 - n1.ru, 8 - egent.ru, 9 - mirkvartir.ru, 10 - moyareklama.ru Можно задать сразу несколько значение через запятую, например, так: source=1,7
        'param[xxx]',//	нет	Дополнительные параметры, вместо xxx должен стоять код параметра. Таких дополнительных параметров может быть несколько. Чтобы узнать код xxx и значение параметра зайдите на сайт и сделайте выборку, задав в фильтре требуемые дополнительные параметры, в адресной строке можно будет увидеть, например, param[1943]=Продам. Чтобы в фильтре увидеть дополнительные параметры, необходимо выбрать категорию второго уровня (на белом фоне), дополнительные параметры будут в нижней строке.
        'format',//	нет	Формат возвращаемых данных. Может принимать значения: json или xml. По-умолчанию json.
        'phone_operator',//	нет	Оператор сотовой связи. Доступные значения параметра смотрите ниже.
        'limit',//	нет	Ограничивает количество объявлений в выборке. Не может быть больше 1000 для платного доступа, и больше 50 для тестового доступа.
        'startid',//	нет	При задании этого параметра, запрос возвращает набор объявлений, значение id которых больше или равно значению заданного параметра. Значения в наборе отсортированы по id по возрастанию.Если задан этот параметр, параметры date1 и date2 не учитываются. С помощью этого параметра можно получать порции новых объявлений, не задавая временной интервал, достаточно задать id последнего полученного объявления плюс 1.
        'withcoords',//	нет	Если параметр равен 1, то возвращаются только объявления с координатами, отличными от 0, т.е. если есть координаты. Потому как, например, у объявлений с egent нет координат.];
    ];

    private $aParams = [

    ];
    /**
     * Параметры поиска
     * @var array
     */
    private $params = [];

    /**
     * Фильтр
     * @var array
     */
    private $filter = [];

    /**
     * @var array
     */
    private $data = [];

    /**
     * @var
     */
    private $message = '';

    /**
     * @var
     */
    private $AdsApi = '';

    public function __construct(array $params = [])
    {
        $this->params = $params;
        $this->AdsApi = new AdsApi();
    }

    /*
     * Получить список объявлений
     * return
     */
    public function get()
    {

        $records = [];
        $result = $this->AdsApi->listObjects($this->params);
        if($result){
            $records = $this->transform($this->AdsApi->answer);
        }else{
            $records = $this->AdsApi->answer;
        }
        return collect($records);
    }

    /*
     * Получить объявлений
     * return
     */
    public function first()
    {
        $record = collect();
        $result = $this->AdsApi->objectId($this->params);
        if($result){
            $record = $this->mapping($this->AdsApi->answer);
        }
        return collect($record);
    }

    /*
     * Подготовка и приведение полученных данных к виду БД
     * @params $data - полученные данные по объявлениям от API
     * return
     */
    private function transform($data)
    {
        $records = [];
        foreach ($data as $value) {
            $records[] = $this->mapping($value);
        }
        return $records;
    }

    /*
     * Сопоставление опций объявления API к виду БД
     * @params $data
     * return array
     */
    private function mapping($data)
    {
        $result = [];
        foreach ($data as $key=>$value){
            if(is_array($value)) {
                if ($key == 'params') {
                    foreach ($value as $i => $item) {
                        $search = array_get($this->mapping, $i);
                        if (array_get($search, 'name') == 'deal_type') {
                            $result['params']['deal_type'] = array_search($item, $this->nedvigimost_type_id);
                        } elseif (array_get($search, 'name') == 'realty_subtype') {
                            $aRealtySubtype = RefBooksValue::select('id', 'value')->ofRb('realty_subtype')->get()->keyBy('value')->toArray();
                            if ($item == 'Вторичка' || $item == 'Новостройка') {
                                if ($item == 'Новостройка') {
                                    $result['params']['is_new_building'] = 1;
                                }
                                $result['params']['realty_subtype'] = array_get($aRealtySubtype, 'Квартира.id');
                            } else {
                                if (array_search($item, $this->cat7)) {
                                    $result['params']['realty_subtype'] = array_search($item, $this->cat7);
                                } else {
                                    $result['params']['realty_subtype'] = array_get($aRealtySubtype, $item . '.id');
                                }
                            }
                        } elseif($i == 'Материал стен') {
                            $result['params']['building_type'] = array_search($item, $this->building_wall);
                        } elseif($i == 'Тип дома' || $i == 'Тип гаража') {
                            $result['params']['building_type'] = array_search($item, $this->building_type);
                        } elseif($i == 'Залог') {
                            $result['params']['deposit_month'] = preg_replace("/[^,.0-9]/", '', $item);
                        } elseif($i == 'Срок аренды') {// || $i == 'Срок сдачи' для новостроек
                            if($item == 'Посуточно'){
                                $result['params']['lease_term'] = 146;
                                $result['price_period'] = 'day';
                            }else{
                                $result['params']['lease_term'] = 149;
                                $result['price_period'] = 'month';
                            }
                        } else {
                            $result['params'][$search['name']] = $item;
                        }
                    }
                }elseif ($key == 'images') {
                    foreach ($value as $item) {
                        $result['images'][] = $item['imgurl'];
                    }
                }else{
                    $result[$key] = $value;
                    if($key == 'coords'){
                        $result['params'][$key]['lat'] = $value['lat'];
                        $result['params'][$key]['lon'] = $value['lng'];
                        $result['ads'][$key] = json_encode($result['params'][$key]);
                    }
                }
            }else{
                $result[$key] = $value;
                if($key == 'cat2_id') {
                    if (in_array($value, [6, 7])) {
                        $result['params']['realty_type'] = 2;
                    } elseif ($value == 5) {
                        $result['params']['realty_type'] = 3;
                    } elseif ($value == 3) {
                        $result['params']['realty_type'] = 1;
                        $oRealtySubtype = RefBooksValue::ofRb('realty_subtype')->where('value','Комната')->first();
                        $result['params']['realty_subtype'] = $oRealtySubtype->id;
                    } else {
                        $result['params']['realty_type'] = 1;
                    }
                }
                if($key == 'source_id'){
                    $result['params']['source'] = $this->sources[$value];
                }
                if($key == 'url'){
                    $result['params']['url'] = $value;
                }
                if(in_array($key,['phone','person','person_type_id','count_ads_same_phone','contactname',])){
                    if($key == 'person'){
                        $result['contact']['name'] = $value;
                    }elseif($key == 'person_type_id'){
                        $result['contact']['person_type'] = array_get($this->person_type,$value) ? array_get($this->person_type,$value) : 1;
                    }elseif($key == 'count_ads_same_phone'){
                        $result['contact']['count_ads'] = $value;
                    }else{
                        $result['contact'][$key] = $value;
                    }
                }
                if(in_array($key,['id','title','description','time'])){
                    if ($key == 'time'){
                        $result['ads']['published_at'] = Carbon::parse($value);
                    } elseif ($key == 'id'){
                        $result['ads']['external_id'] = $value;
                    } else {
                        $result['ads'][$key] = $value;
                    }
                }
            }
        }
        $result['params']['address'] = $result['region'].', '.$result['city1'].', '.$result['address'];
        $result['ads']['address'] = $result['region'].', '.$result['city1'].', '.$result['address'];

        return [
            'contact' => array_get($result,'contact',[]),
            'ads' => array_get($result,'ads',[]),
            'params' => array_get($result,'params',[]),
            'images' => array_get($result,'images',[]),
            'price' => [
                'value' => array_get($result,'price',0),
                'period' => array_get($result,'price_period',''),
            ],
        ];
    }

    /*
     * Получение параметра
     * @params $value
     * return array
     */
    private function parametr($key,$value)
    {
        dd('parameter',$key,$this->matching[$key],$value);
        if(is_array($value)){
            if($key == 'params'){

            }
        }
        $this->matching[$key];
        return $value;
    }

    /*
     * Получение опции
     * @params $value
     * return array
     */
    private function option($key,$value)
    {
        dd($value);
        return $value;
    }

    /*
     * Получить список объявлений
     * @params $data - дополнительные параметры поиска
     * return
     */
    public function filter($data)
    {
        $this->filter = $data;
        return $this;
    }

    /*
     * Получить список объявлений
     * @params $param
     * @params $driver - тип работы с БД (orm, sql)
     * return
     */
    public function list($param,$driver)
    {
        switch ($driver){
            case 'orm' : $result = $this->parser->getOrm($param);
                        break;
            case 'sql' : $result = $this->parser->getSql($param);
                        break;
            default : $result = collect();
        }
        dd($result);

    }

    /*
     * Получить объявлениe по id
     * @params $id
     * @params $driver - тип работы с БД (orm, sql)
     * return
     */
    public function one($id,$driver)
    {
        $result = $this->parser->getOne($id);
        /*
        switch ($driver){
            case 'orm' : $result = $this->parser->getOneOrm($id);
                break;
            case 'sql' : $result = $this->parser->getSql($param);
                break;
            default : $result = collect();
        }
        */
        dd($result);
    }

}