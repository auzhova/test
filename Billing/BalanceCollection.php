<?php

namespace App\Services\Billing;

use App\Models\Member;
use App\Models\Balance;
use App\Registries\MemberRegistry;
use Cartalyst\Sentinel\Users\EloquentUser;
use Illuminate\Support\Collection;
use App\Services\Billing\Balance as BalanceService;

class BalanceCollection
{
    private $aMemberData = [];

    public function __construct($nUserId = 0)
    {
        if ($nUserId === 0) {
            $this->aMemberData = MemberRegistry::getInstance()->getMember()->toArray();
        } else {
            $this->aMemberData = (new Member(EloquentUser::find($nUserId)))->get()->toArray();
        }
    }

    /**
     * Баланс польователя
     *
     * @return mixed
     */
    private function getSelf()
    {
        $oBalance = Balance::where('type', 'user')
            ->where('item_id', $this->aMemberData['user']['id'])
            ->first();

        if (is_null($oBalance)) {
            $oBalance = $this->create();
        }

        return $this->transform($oBalance);
    }

    /**
     * Баланс агенства
     *
     * @return mixed
     */
    private function getAgency()
    {
        $oBalance = Balance::where('type', 'agency')
            ->where('item_id', $this->aMemberData['agency']['id'])
            ->first();

        return $this->transform($oBalance);
    }

    /**
     * Трансформация баланса
     *
     * @param $oBalance
     * @return mixed
     */
    private function transform($oBalance)
    {
        $oCollection = (new BalanceService($oBalance->id, $this->aMemberData['user']['id']))->getInfo();
        $oCollection = collect($oCollection);
        return $oCollection;
    }

    /**
     * Вытащить по умолчанию
     *
     * @return mixed
     */
    public function getDefault()
    {
        if ($this->isBroker()) {
            return $this->getAgency();
        } else {
            return $this->getSelf();
        }
    }

    public function getAll()
    {
        $oCollection = collect([]);

        $oBalances = Balance::where('type', 'agency')
            ->where('item_id', $this->aMemberData['agency']['id'])
            ->get();

        if (count($oBalances) !== 0) {
            foreach ($oBalances as $oBalance) {
                $oCollection->push($this->transform($oBalance));
            }
        }

        if (!$this->isBroker()) {
            $oBalances = Balance::where('type', 'user')
                ->where('item_id', $this->aMemberData['user']['id'])
                ->get();

            if (count($oBalances) !== 0) {
                foreach ($oBalances as $oBalance) {
                    $oCollection->push($this->transform($oBalance));
                }
            }
        }

        return $oCollection;
    }

    /**
     * Проверка роли мембера
     *
     * @return bool
     */
    private function isBroker()
    {
        return in_array($this->aMemberData['role']['slug'], [
            'admin',
            'broker',
//            'director',
            'listman',
            'superbroker'
        ]);
    }

    /**
     * Создание баланса
     * - для юзера
     * - для агенства
     *
     * @param int $id
     * @param string $type
     * @return mixed
     */
    public function create($id = 0, $type = 'user')
    {
        if (!in_array($type, ['user', 'agency'])) {
            dd('!in_array('.$type.', [\'user\', \'agency\'])');
        }
        $id = $id !== 0 ? $id : $this->aMemberData['user']['id'];
        $oBalance = Balance::where('type', $type)
            ->where('item_id', $id)
            ->first();

        if (is_null($oBalance)) {
            $method = 'createFor'.title_case($type).'Balance';
            if (method_exists($this, $method)) {
                $oBalance = $this->{$method}($id, $type);
            }
        }
        return $oBalance;
    }

    /**
     * Особое добавление баланса агенства
     *
     * @param $id
     * @param $type
     * @return mixed
     */
    private function createForAgencyBalance($id, $type)
    {
        return Balance::create([
            'item_id' => $id,
            'type' => $type,
            'status' => 1
        ]);
    }

    /**
     * Особое добавление баланса юзера
     *
     * @param $id
     * @param $type
     * @return mixed
     */
    private function createForUserBalance($id, $type)
    {
        return Balance::create([
            'item_id' => $id,
            'type' => $type,
            'status' => 1
        ]);
    }



    /**
     * Множественное создание балансов
     *
     * @param $aUserId array users id
     * @return array
     */
    public function batchCreate($aUserId)
    {
        $data = [];
        foreach ($aUserId as $id) {
            if ($id === 0) {
                continue;
            }
            $oBalance = Balance::where('type', 'user')
                ->where('item_id', $id)
                ->first();
            if (is_null($oBalance)) {
                $data[] = [
                    'item_id' => $id,
                    'type' => 'user',
                    'status' => 1
                ];
            }
        }
        if (!empty($data)) {
            Balance::insert($data);
        }
        return $data;
    }
}
