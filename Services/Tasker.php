<?php

namespace App\Services;

use App\Domain\Calendar;
use App\Models\Agent;
use App\Models\CalendarEvent;
use App\Models\CalendarEventsType;
use App\Models\TaskerChecklist;
use App\Models\TaskerRel;
use App\Models\TaskerTask;
use App\Registries\MemberRegistry as Member;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class Tasker
{
    public $nAgentId = 0;

    public $aAgents = [];

    public function __construct($agent_id)
    {
        $this->nAgentId = $agent_id;
        $this->member   = Member::getInstance();
        $this->aAgents  = Agent::active()->where('agency_id', $this->member->get('agency.id'))->pluck('id')->toArray();
    }

    /**
     * Получает данные задачи по id для просмотра
     * @param $id
     * @return mixed
     */
    public function get($id)
    {
        if (!$this->checkRights($id, 'view')) {
            return ['status' => 'error', 'message' => 'Недостаточно прав', 'status_code' => 403, 'data' => collect()];
        } else {
            $oData = TaskerTask::whereId($id)->with('creator', 'responsible', 'stage', 'calendar', 'checklist', 'relContact', 'relListing', 'relDeal', 'relCoresponsible',//'relLead',
                'relObserver')->first();

            return ['status' => 'success', 'message' => 'ok', 'status_code' => 200, 'data' => $oData];
        }
    }

    /**
     * Получает связи по задаче
     * @param $id
     * @return mixed
     */
    public function getRels($id)
    {
        $oRecords = TaskerRel::where('task_id', $id)->get();
        $aData    = []; //['contact' => [],'listing' => [],'lead' => [],'deal' => []];
        foreach ($oRecords as $oRecord) {
            switch ($oRecord->type) {
                case 'contact':
                    if ($oRecord->item_id) {
                        $oContact         = \App\Models\Client::find($oRecord->item_id);
                        $aData['contact'] = ['id' => $oContact->id, 'name' => $oContact->name];
                    }
                    break;
                case 'listing':
                    if ($oRecord->item_id) {
                        $oListing         = \App\Models\Listing::find($oRecord->item_id);
                        $aData['listing'] = ['id' => $oListing->id, 'name' => $oListing->title];
                    }
                    break;
                    /*
                case 'lead':
                    if ($oRecord->item_id) {
                        $oLead         = \App\Models\Lead::find($oRecord->item_id);
                        $aData['lead'] = ['id' => $oLead->id, 'name' => $oLead->title];
                    }
                    break;
                    */
                case 'deal':
                    if ($oRecord->item_id) {
                        $oDeal         = \App\Models\DealBeta::where('deals_beta.id', $oRecord->item_id)
                                        ->leftJoin('leads', 'deals_beta.lead_id', '=', 'leads.id')
                                        ->select('deals_beta.id', 'leads.title as title')->first();
                        if(is_null($oDeal)){
                            $oDeal         = \App\Models\Deal::find($oRecord->item_id);
                        }
                        $aData['deal'] = ['id' => $oDeal->id, 'name' => $oDeal->title];
                    }
                    break;
                case 'co-responsible':
                    if ($oRecord->item_id) {
                        $oContact                 = \App\Models\Agent::find($oRecord->item_id);
                        $aData['coresponsible'][] = ['id' => $oContact->id, 'name' => $oContact->name];
                    }
                    break;
                case 'observer':
                    if ($oRecord->item_id) {
                        $oContact            = \App\Models\Agent::find($oRecord->item_id);
                        $aData['observer'][] = ['id' => $oContact->id, 'name' => $oContact->name];
                    }
                    break;
            }
        }

        return $aData;
    }

    /**
     * Получает задачи по типу связи и ид сущности
     * @param $aData = [item_id - ид сущности,type - ['listing','lead','contact','deal']
     * @return mixed
     */
    public function getTasksByRels($aData)
    { 
        $oTasksId = TaskerRel::where('item_id', $aData['item_id'])->where('type', $aData['type'])->pluck('task_id');
        $oTasks   = TaskerTask::whereIn('id', $oTasksId)->with('creator', 'responsible', 'stage')->get();
        return $oTasks;
    }

    /**
     * Получает задачи бп и номеру стадии
     * @param $aData = [id - ид сделки,'stage_nom' - номер стадии для бп]//
     * @return mixed
     */
    public function getTasksByBpmStage($aData)
    {
        $oDeal = \App\Models\Deal::find($aData['id']);
        $oBpmUnit = \App\Models\BpmUnit::find($oDeal->bpm_unit_id);
        $oTemplates = \App\Models\BpmTasksStage::where('bpm_type_id',$oBpmUnit->bpm_type_id)->where('stage_nom',$aData['stage_nom'])->pluck('task_id');
        $oTaskTemplates = TaskerTask::whereIn('template_id',$oTemplates)->pluck('id');
        $oTaskRel = TaskerRel::where('item_id', $aData['id'])->where('type', 'deal')->whereIn('task_id',$oTaskTemplates)->pluck('task_id');
        $oTasks   = TaskerTask::whereIn('id', $oTaskRel)->with('creator', 'responsible', 'stage')->get();
        return $oTasks;
    }
    
    /**
     * Получает список задач
     * @param $aFilters
     * @return mixed
     */
    public function getAll($aFilters)
    {
        $aInferiors = $this->aAgents; //$this->member->getInferiorsAll([], true)->pluck('id')->toArray();
        $oQuery     = TaskerTask::whereIn('tasker_tasks.responsible_id', $aInferiors)->with('responsible', 'creator', 'stage');
        //фильтрация
        if (!empty($aFilters)) {
            foreach ($aFilters as $key => $value) {
                if (!empty($value)) {
                    $oQuery = $oQuery->where('tasker_tasks.' . $key, $value);
                }
            }
        }

        return $oQuery;
    }

    /**
     * Получение списка задач
     * @param $aFilters
     * @param bool $isFlatten
     * @param string $sorting
     * @return LengthAwarePaginator
     */

    public function getList($aFilters, bool $isFlatten = false, string $sorting = 'priority|asc'): LengthAwarePaginator {
        $aInferiors = $this->aAgents;
        # Для сортировки нужный джойны
        $oQuery = TaskerTask::query()
            ->select(['tasker_lists.*', 'responsibles_alias.name as responsible_name', 'creators_alias.name as creator_name', 'stages_alias.title as stage_name'])
            ->leftJoin('contacts as responsibles_alias', function(JoinClause $join) {
                return $join->on('responsibles_alias.id', '=', 'tasker_lists.responsible_id');
            })
            ->leftJoin('contacts as creators_alias', function(JoinClause $join) {
                return $join->on('creators_alias.id', '=', 'tasker_lists.creator_id');
            })
            ->leftJoin('tasker_stages as stages_alias', function(JoinClause $join) {
                return $join->on('stages_alias.id', '=', 'tasker_lists.stage_id');
            })
            ->whereIn('tasker_lists.responsible_id', $aInferiors)
            ->where('tasker_lists.is_template', 0)
            ->with(/* Для обратной совместимости жадная загрузка creator, responsible, stage*/'creator','responsible','stage', 'relCoresponsible', 'relObserver');

        if ($sorting) {
            [$primarySortable, $primaryDestination] = explode('|', $sorting);
            $oQuery->orderBy($primarySortable, $primaryDestination)->orderBy('created_at', 'desc');
        }

        if (!$isFlatten) {
            $oQuery->with(['children.creator', 'children.responsible', 'children.stage']);
        }

        $collection = $this->withFilters($oQuery, $aFilters)->get()->keyBy('id');
        $collection = $collection->reject(function(TaskerTask $item) use ($collection) {
            if ($item->is_rejected) {
                return true;
            }

            if (($groupTask = $collection->get($item->parent_id)) && !$groupTask->is_rejected) {
                $groupTask->setAttribute('is_rejected', false);
                $item->setAttribute('is_rejected', true);

                return true;
            }

            $item->setAttribute('is_rejected', false);

            return false;
        });

        $perPage = request()->has('per_page') ? (int)request()->per_page : 20;
        $page    = request()->has('page') ? (int)request()->page : 1;

        return new Paginator($collection->forPage($page, $perPage)->values(), $collection->count(), $perPage, request()->page);
    }

    /**
     * Получение задачи в интервале
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param array $aFilters
     * @param bool $isFlatten
     * @return Collection
     */
    public function getListBetweenDates(Carbon $startDate, Carbon $endDate, array $aFilters = [], bool $isFlatten = false): Collection {
        $aInferiors = $this->aAgents;
        /** @var Builder $oQuery */
        $oQuery = TaskerTask::query()->whereIn('responsible_id', $aInferiors)->where('is_template', 0)
            ->whereBetween('deadline_at', [$startDate, $endDate]);
        if (!$isFlatten) {
            $oQuery->with(['children.creator', 'children.responsible', 'children.stage']);
        }

        return $this->withFilters($oQuery, $aFilters)->get();
    }

    /**
     * Получение списка шаблонов задач
     * @param $aFilters
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection
     */

    public function getListTemplate($aFilters)
    {
        $aInferiors = $this->aAgents; //$this->member->getInferiorsAll([], true)->pluck('id')->toArray();
        $oQuery     = TaskerTask::where('creator_id', $this->nAgentId)->with('responsible', 'creator', 'stage')->where('is_template', 1)->orderBy('id', 'desc');
//        $oQuery = TaskerTask::whereIn('responsible_id',$aInferiors)
        //                            ->with('responsible','creator','stage')->where('is_template',1)->orderBy('id','desc');
        //        if(!empty($aFilters)){
        //            if(empty($aFilters['responsible_id']) && empty($aFilters['creator_id'])){
        //                $oQuery = $oQuery->where(function($query){
        //                        $query->orWhere('responsible_id',$this->nAgentId);
        //                        $query->orWhere('creator_id',$this->nAgentId);
        //                });
        //            }
        //            foreach ($aFilters as $key=>$value){
        //                if(!empty($value)){
        //                    switch ($key){
        //                        case 'title': // название
        //                            $strSearch = trim($value);
        //                            $oQuery = $oQuery->where('title', 'like', '%'.$strSearch.'%');
        //                            break;
        //                        case 'deadline_at'://дедлайн
        //                            $dDate = Carbon::parse($value);
        //                            $oQuery = $oQuery->where('deadline_at', '<=', $dDate);
        //                            break;
        //                        default: // "свободная" фильтрация по параметрам
        //                            $oQuery = $oQuery->where($key, $value);
        //                            break;
        //                    }
        //                }
        //            }
        //        }
        $oResult = $oQuery->paginate(5);

        return $oResult;
    }

    /**
     * Фильтрация задач
     * @param Builder|static $oQuery
     * @param array      $aFilters
     * @return Builder
     */
    private function withFilters($oQuery, $aFilters): Builder {
        if (!empty($aFilters)) {
            if (empty($aFilters['responsible_id']) && empty($aFilters['creator_id']) && !$this->member->isBrokerOrHigher()) {
                $oQuery = $oQuery->where(function($query) use ($aFilters) {

                    /** @var Builder $query */
                    // -1. Если это - руководитель
                    if($this->member->isDirector()) {
                        $query->whereIn('tasker_lists.responsible_id', $this->member->getInferiors()->pluck('id'));
                    }

                    // 0. Если пользователь является непосредственным ответственным
                    if (empty($aFilters['responsible_id'])) {
                        $query->orWhere('tasker_lists.responsible_id', $this->nAgentId);
                    }

                    // 1. Если пользователь является создателем задачи
                    if (empty($aFilters['creator_id'])) {
                        $query->orWhere('tasker_lists.creator_id', $this->nAgentId);
                    }

                    // 2. Если пользователь является соисполнителем задачи
                    $aTasksCoRes = empty($aFilters['coresponsible_id']) ? TaskerRel::query()->where('item_id', $this->nAgentId)->where('type',
                        'co-responsible')->pluck('task_id')->toArray() : TaskerRel::query()->where('item_id', $aFilters['coresponsible_id'])
                        ->where('type', 'co-responsible')->pluck('task_id')->toArray();
                    if ($aTasksCoRes) {
                        $query->orWhereIn('tasker_lists.id', $aTasksCoRes);
                    }

                    // 3. Если пользователь является наблюдателем по задаче
                    $aTasksObser = empty($aFilters['observer_id']) ? TaskerRel::query()->where('item_id', $this->nAgentId)->where('type', 'observer')->pluck('task_id')->toArray() :
                        TaskerRel::query()->where('item_id', $aFilters['observer_id'])->where('type', 'observer')->pluck('task_id')->toArray();
                    if ($aTasksObser) {
                        $query->orWhereIn('tasker_lists.id', $aTasksObser);
                    }
                });
            }

            // 4. Показывать завершенные задачи, если есть соответствующий флаг и он true
            if (array_key_exists('is_show_finished_tasks', $aFilters)) {
                $oQuery = $aFilters['is_show_finished_tasks'] ? $oQuery : $oQuery->where('stage_id', '!=', 4);
                unset($aFilters['is_show_finished_tasks']);
            }

            // 5. Фильтрация по остальным параметрам (для поиска)
            foreach ($aFilters as $key => $value) {
                if (!empty($value)) {
                    switch ($key) {
                        case 'title': // название
                            $strSearch = trim($value);
                            $oQuery    = $oQuery->where('tasker_lists.title', 'like', '%' . $strSearch . '%');
                            break;
                        case 'deadline_at': //дедлайн
                            $dDate  = Carbon::parse($value);
                            $oQuery = $oQuery->where('tasker_lists.deadline_at', '<=', $dDate);
                            break;
                        case 'coresponsible_id': //соисполнитель
                            $aTasks = TaskerRel::query()->where('item_id', $value)->where('type', 'co-responsible')->pluck('task_id')->toArray();
                            $oQuery = $oQuery->whereIn('tasker_lists.id', $aTasks);
                            break;
                        case 'observer_id': //наблидатель
                            $aTasks = TaskerRel::query()->where('item_id', $value)->where('type', 'observer')->pluck('task_id')->toArray();
                            $oQuery = $oQuery->whereIn('tasker_lists.id', $aTasks);
                            break;
                        default: // "свободная" фильтрация по параметрам
                            $oQuery = $oQuery->where($key, $value);
                            break;
                    }
                }
            }
        }

        return $oQuery;
    }

    /**
     * Получение списка задач для канбана
     * @param $aFilters
     * @param bool $withTemplates
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection
     */

    public function getListKanban($aFilters, bool $onlyTasks = false)
    {
        $aInferiors = $this->aAgents; //$this->member->getInferiorsAll([], true)->pluck('id')->toArray();
        /** @var Builder $oQuery */
        $oQuery = TaskerTask::query()->whereIn('responsible_id', $aInferiors)->with('responsible', 'creator', 'stage', 'comments', 'checklist')->orderBy('priority');
        if($onlyTasks) {
            $oQuery = $oQuery->where('is_template', 0);
        }
        return $this->withFilters($oQuery, $aFilters);
    }

    /**
     * Создать задачу
     * @param $aData
     * @return mixed
     */
    public function create($aData)
    {
        /** @var TaskerTask|null $mainTask */
        [$mainTask, $oTask] = [null, null];
        if (!empty($aData['is_group']) && $aData['is_group'] !== false) {
            $responsibles = array_merge([array_get($aData, 'responsible_id', $this->nAgentId)], array_get($aData, 'group_responsibles', []));
        } else {
            $responsibles = [array_get($aData, 'responsible_id', $this->nAgentId)];
        }

        foreach ($responsibles as $responsible) {
            $aTask = [
                'title'          => $aData['title'],
                'stage_id'       => 1, // ожидает
                'creator_id'     => !empty($aData['creator_id']) ? $aData['creator_id'] : $this->nAgentId,
                'responsible_id' => $responsible,
                'description'    => $aData['description'],
                'deadline_at'    => (isset($aData['deadline_at']) && !empty($aData['deadline_at'])) ? Carbon::parse($aData['deadline_at']) : null,
                'is_template'    => !empty($aData['is_template']),
                'template_id'    => (array_get($aData, 'template_id') && !empty($aData['template_id'])) ? $aData['template_id'] : 0,
                'accept_after'   => array_get($aData, 'accept_after', false),
                'can_delegate'   => !empty($aData['can_delegate']) ? json_encode($aData['can_delegate']) : null,
                'priority'       => 0,
                'is_repetitive'  => !$mainTask ? array_get($aData, 'is_repetitive', false) : false,
                'is_group'       => \count($responsibles) > 1 && !$mainTask,
                'parent_id'      => $mainTask ? $mainTask->id : null
            ];
            if (empty($aTask['parent_id']) && $aTask['is_repetitive']) {
                $aTask['repetitive_options'] = array_get($aData, 'repetitive_options');
            }

            $oTask = TaskerTask::create($aTask);
            if(!$mainTask) {
                $mainTask = $oTask;
            }
            if ($oTask->is_template == 0) {
                $this->updateRels($oTask, $aData);
                $this->updateCalendarEvent($oTask, null, $aData, 'create');
                Notification::notifyTask($oTask, Notification::CREATING_TASK_ACTION);
//            Notification::Task($oTask->id, $oTask->stage_id);
            }
        }

        return ['id' => $mainTask ? $mainTask->id : $oTask->id, 'status_code' => 200];
    }

    /**
     * Обновить данные в задаче
     * @param $aData
     * @return mixed
     */
    public function update($aData)
    {        
        if (!$this->checkRights($aData['id'], 'edit')) {
            return ['message' => 'Не достаточно прав', 'status_code' => 403];
        }
        /** @var TaskerTask $oTask */
        $oTask = TaskerTask::query()->find($aData['id']);
        if ($oTask === null) {
            return ['message' => 'Запись не найдена', 'status_code' => 404];
        }
        $oTaskOld = clone $oTask;
        switch (true) {
            case empty($aData['deadline_at']):
                $deadlineAt = null;
                break;
            case ($newDate = Carbon::parse($aData['deadline_at'])) !== $oTask->deadline_at:
                $deadlineAt = $newDate;
                break;
            default:
                $deadlineAt = $oTask->deadline_at;
                break;
        }
        $sCanDelagete = !$oTask->is_template ? !empty($aData['can_delegate']) ? json_encode($aData['can_delegate']) : $oTask->can_delegate : null;
        $isRepetitive = array_get($aData, 'is_repetitive', $oTask->is_repetitive);
        $oTask->update([
            'title'          => $aData['title'],
            'description'    => $aData['description'],
            //'stage_id' => (array_get($aData,'stage_id') && $aData['stage_id'] != $oTask->stage_id) ? $aData['stage_id'] : $oTask->stage_id,
            'responsible_id' => (isset($aData['responsible_id']) && !empty($aData['responsible_id'])) ? $aData['responsible_id'] : $oTask->responsible_id,
            'updater_id'     => $this->nAgentId,
            'accept_after'   => array_get($aData, 'accept_after', $oTask->accept_after),
            'can_delegate'   => $sCanDelagete,//!empty($aData['can_delegate']) ? json_encode($aData['can_delegate']) : $oTask->can_delegate,
            'deadline_at'    => $deadlineAt, //(!empty(Carbon::parse($aData['deadline_at'])) && Carbon::parse($aData['deadline_at']) != $oTask->deadline_at) ? Carbon::parse($aData['deadline_at']) : $oTask->deadline_at,
        ]);

        if($isRepetitive && !empty($aData['repetitive_options'])) {
            $oTask->update([
                'repetitive_options' => array_merge((array) $oTask->repetitive_options, (array) $aData['repetitive_options']),
                'is_repetitive'  => $isRepetitive,
            ]);
        } elseif(!$isRepetitive) {
            $repetitiveOptions = $oTask->repetitive_options;
            unset($repetitiveOptions['next_repeat']);
            $oTask->update([
                'repetitive_options' => $repetitiveOptions,
                'is_repetitive'  => $isRepetitive
            ]);
        }

        if ($oTask->is_template == 0) {
            $this->updateRels($oTask, $aData);
            $this->updateCalendarEvent($oTask, $oTaskOld, $aData, 'update');
            if($oTaskOld->responsible_id != $oTask->responsible_id){
                Notification::notifyTask($oTask, Notification::UPDATING_TASK_ACTION);
            }
            // todo: Отправка уведомления при изменении отключена до уточнения, нужна ли она.
//            Notification::notifyTask($oTask, Notification::UPDATING_TASK_ACTION);
//            Notification::Task($oTask->id, $oTaskOld->stage_id);
        }
        $remindersIds = [];
        if (!$oTask->is_group && $reminders = array_get($aData, 'reminders')) {
            $typeId = CalendarEventsType::query()->where('name', CalendarEventsType::TASK_EVENT_TYPE_USER)->first()->id;
            $domainCalendar = new Calendar();
            $filteredIds = array_filter(array_pluck($reminders, 'id'));
            $reminderModels = CalendarEvent::query()->whereIn('id', $filteredIds)->get()->keyBy('id');
            CalendarEvent::query()->where([
                'item_id' => $oTask->id,
                'type_id' => $typeId
            ])->whereNotIn('id', $reminderModels->pluck('id')->toArray())->update([
                'status'     => false,
                'deleted_at' => Carbon::now()
            ]);
            foreach ($reminders as $reminder) {
                if (array_key_exists('id', $reminder) && $reminderModels->has($reminder['id'])) {
                    $model = $reminderModels->get($reminder['id']);
                    // Если эвент существует
                    $time = Carbon::parse($reminder['date']);
                    if ($reminder['text'] !== $model->title || $reminder['responsible_id'] !== $model->responsible_id
                        || $time->format('d.m.Y H:i') !== $model->started_at->format('d.m.Y H:i')
                    ) {
                        // Обновляем событие календаря
                        $domainCalendar->updateEvent([
                            'id'             => $reminder['id'],
                            'started_at'     => $time,
                            'finished_at'    => $time,
                            'title'          => $reminder['text'] ?? '',
                            'description'    => $reminder['text'] ?? '',
                            'responsible_id' => $reminder['responsible_id']
                        ]);
                    }
                    $remindersIds[] = $reminder['id'];
                    $reminderModels->forget($model->id);
                } else {
                    // Создаем событие календаря
                    $response = $domainCalendar->addEvent([
                        'started_at'     => $time = Carbon::parse($reminder['date']),
                        'finished_at'    => $time,
                        'title'          => $reminder['text'] ?? '',
                        'description'    => $reminder['text'] ?? '',
                        'responsible_id' => $reminder['responsible_id'],
                        'type_id'        => $typeId,
                        'item_id'        => $oTask->id,
                        'creator_id'     => $this->nAgentId
                    ]);
                    $remindersIds[] = $response['calendar_id'];
                }
            }
        }

        return ['id' => $oTask->id, 'reminders_ids' => $remindersIds,'status_code' => 200];
    }

    /**
     * Обновление связей
     * @param $oTask
     * @param $aData
     * @return mixed
     */
    public function updateRels($oTask, $aData)
    {
        if (isset($aData['rel_contact']) && !empty($aData['rel_contact'])) {
            TaskerRel::updateOrCreate(['task_id' => $oTask->id, 'type' => 'contact'], ['task_id' => $oTask->id, 'type' => 'contact', 'item_id' => $aData['rel_contact']]);
        } else {
            TaskerRel::where('task_id', $oTask->id)->where('type', 'contact')->delete();
        }
        if (isset($aData['rel_lead']) && !empty($aData['rel_lead'])) {
            TaskerRel::updateOrCreate(['task_id' => $oTask->id, 'type' => 'lead'], ['task_id' => $oTask->id, 'type' => 'lead', 'item_id' => $aData['rel_lead']]);
        } else {
            TaskerRel::where('task_id', $oTask->id)->where('type', 'lead')->delete();
        }
        if (isset($aData['rel_deal']) && !empty($aData['rel_deal'])) {
            TaskerRel::updateOrCreate(['task_id' => $oTask->id, 'type' => 'deal'], ['task_id' => $oTask->id, 'type' => 'deal', 'item_id' => $aData['rel_deal']]);
        } else {
            TaskerRel::where('task_id', $oTask->id)->where('type', 'deal')->delete();
        }
        if (isset($aData['rel_listing']) && !empty($aData['rel_listing'])) {
            TaskerRel::updateOrCreate(['task_id' => $oTask->id, 'type' => 'listing'], ['task_id' => $oTask->id, 'type' => 'listing', 'item_id' => $aData['rel_listing']]);
        } else {
            TaskerRel::where('task_id', $oTask->id)->where('type', 'listing')->delete();
        }
        if (isset($aData['rel_coresponsible']) && !empty($aData['rel_coresponsible'])) {
            TaskerRel::where('task_id', $oTask->id)->where('type', 'co-responsible')->delete();
            foreach ($aData['rel_coresponsible'] as $nResponsible) {
                TaskerRel::updateOrCreate(['task_id' => $oTask->id, 'type' => 'co-responsible', 'item_id' => $nResponsible],
                    ['task_id' => $oTask->id, 'type' => 'co-responsible', 'item_id' => $nResponsible]);
            }
        } else {
            TaskerRel::where('task_id', $oTask->id)->where('type', 'co-responsible')->delete();
        }
        if (isset($aData['rel_observer']) && !empty($aData['rel_observer'])) {
            TaskerRel::where('task_id', $oTask->id)->where('type', 'observer')->delete();
            foreach ($aData['rel_observer'] as $nObserver) {
                TaskerRel::updateOrCreate(['task_id' => $oTask->id, 'type' => 'observer', 'item_id' => $nObserver], ['task_id' => $oTask->id, 'type' => 'observer', 'item_id' => $nObserver]);
            }
        } else {
            TaskerRel::where('task_id', $oTask->id)->where('type', 'observer')->delete();
        }
    }

    /**
     * Обновление события в календаре
     * @param $oTask
     * @param $aData
     * @param $sAction
     * @return mixed
     */
    private function updateCalendarEvent($oTask, $oTaskOld, $aData, $sAction)
    {
        Log::info('updateCalendarEvent');
        Log::info($oTask);
        // С: изменение, чтобы при дедлайн = null не отображалась напоминалка в календаре
//        if ($oTask->deadline_at) {
            $aData['deadline_at'] = $oTask->deadline_at;
            $aTask                = $oTask->toArray();
            $oCalendarEvent = CalendarEvent::where('type_id', 9)->where('item_id', $oTask->id)->where('responsible_id', $oTask->responsible_id)->first();
            if ($sAction == 'create') {
                (new Calendar())->CalendarEvent($oTask->id, $aTask, 9, 'create', $aData['title'], $oTask->creator_id);
            }elseif(empty($oCalendarEvent)){
                (new Calendar())->CalendarEvent($oTask->id, $aTask, 9, 'create', $aData['title'], $oTask->creator_id);
            } else {
//                $oCalendar = \App\Models\CalendarEvent::where('type_id',9)->where('item_id',$oTask->id)->first();
                //                if(empty($oCalendar)){
                //                    (new Calendar())->CalendarEvent($oTask->id,$aTask,9,'create',$aData['title'],$aData['creator_id']);
                //                }else{
                (new Calendar())->CalendarEvent($oTask->id, $aTask, 9, 'update', $aData['title']);
                if ($oTaskOld && $oTaskOld->responsible_id != $oTask->responsible_id) {
                    CalendarEvent::query()
                        ->where([
                            'type_id'        => 9,
                            'item_id'        => $oTask->id,
                            'responsible_id' => $oTaskOld->responsible_id
                        ])
                        ->update(['deleted_at' => Carbon::now()]);
                }
//                }
            }
    }

    /**
     * Удалить задачу
     * @param int|TaskerTask $taskOrId
     * @return mixed
     * @throws \Exception
     */
    public function delete($taskOrId) {
        $oTask = $taskOrId instanceof TaskerTask ? $taskOrId : TaskerTask::query()->find($taskOrId);
        if (!$this->checkRights($oTask->id, 'delete')) {
            return ['message' => 'Недостаточно прав', 'status_code' => 403];
        }
        /** @var TaskerTask $oTask */
        $oTask->update([
            'remover_id' => $this->nAgentId,
            'status'     => 0,
        ]);
        $oTask->delete();

        CalendarEvent::query()->where([
            'type_id' => 10,
            'item_id' => $oTask->id
        ])->update([
            'status'     => false,
            'deleted_at' => Carbon::now()
        ]);

        return ['id' => $oTask->id, 'status_code' => 200];
    }

    /**
     * @param array $aData
     * @return bool
     */
    public function calendarDragTask(array $aData): bool {
        return TaskerTask::query()->where('id', $aData['id'])->update([
            'deadline_at' => Carbon::parse($aData['deadline_at'])
        ]);
    }

    /**
     * Проверка прав
     * @param string $taskId
     * @param string $sType view/edit
     * @return mixed
     */
    public function checkRights($taskId, $sType)
    {
        $aResult = $this->getRights($taskId, true);

        return array_get($aResult, $sType, false);
    }

    /**
     * Получение прав
     * @param $taskId
     * @param bool $withTrashedInferiors
     * @return mixed
     */
    public function getRights($taskId, bool $withTrashedInferiors = false)
    {
        $aResult         = ['view' => false, 'edit' => false, 'delete' => false, 'changeStatus' => false];
        $oTask           = TaskerTask::find($taskId);
        $aInferiors      = ($withTrashedInferiors ? $this->member->getInferiorsAll([], $withTrashedInferiors) : $this->member->getInferiors())->pluck('id')->toArray();
        $aTaskReksCoResp = TaskerRel::where('type', 'co-responsible')->where('task_id', $taskId)->pluck('item_id')->toArray();
        $aTaskReksObserv = TaskerRel::where('type', 'observer')->where('task_id', $taskId)->pluck('item_id')->toArray();
        switch (true) {
            case $oTask->creator_id === (int)$this->nAgentId:
            case \in_array($oTask->creator_id, $aInferiors, true):
                $aResult = [
                    'view'         => true,
                    'edit'         => true,
                    'delete'       => true,
                    'changeStatus' => true
                ];
                break;
            case \in_array($oTask->responsible_id, $aInferiors, true):
                $aResult['view']         = true;
                $aResult['changeStatus'] = true;
                break;
            case \in_array($this->nAgentId, $aTaskReksCoResp, true) || \in_array($this->nAgentId, $aTaskReksObserv, true):
                $aResult['edit'] = false;
                $aResult['view'] = true;
                break;
        }
//
//
//        if ($oTask->creator_id == $this->nAgentId) {
//            $aResult = ['view' => true, 'edit' => true, 'delete' => true, 'changeStatus' => true];
//        } elseif (in_array($oTask->responsible_id, $aInferiors)) {
//            $aResult['view']         = true;
//            $aResult['changeStatus'] = true;
//        } elseif (in_array($oTask->creator_id, $aInferiors)) {
//            $aResult['edit']         = true;
//            $aResult['view']         = true;
//            $aResult['changeStatus'] = true;
//        } elseif (in_array($this->nAgentId, $aTaskReksCoResp) || in_array($this->nAgentId, $aTaskReksObserv)) {
//            $aResult['edit'] = false;
//            $aResult['view'] = true;
//        }

        // dd($aResult, in_array($oTask->responsible_id, $aInferiors), $aInferiors, $this->member);

        return $aResult;
    }

    /**
     * Изменение стадии задачи
     * @param $aData
     * @return mixed
     */
    public function setStageTask($aData)
    {
        /** @var TaskerTask $oTask */
        $oTask = TaskerTask::find($aData['id']);
        if (is_null($oTask)) {
            return ['message' => 'Запись не найдена', 'status_code' => 404];
        }
        if (!$this->checkRights($aData['id'], 'changeStatus')) {
            return ['message' => 'Недостаточно прав', 'status_code' => 403];
        }
        $oldStageId = $oTask->stage_id;
        $oTask->update(['stage_id' => $aData['stage_id']]);
        // todo: раскоментировать нотификацию
        Notification::notifyTask($oTask->setAttribute('old_stage_id', $oldStageId), Notification::CHANGE_STAGE_TASK_ACTION);
        //Notification::Task($oTask->id, $oTaskOld->stage_id);

        return ['id' => $oTask->id, 'status_code' => 200];
    }

    /**
     * Создать пункт чек-листа
     * @param $aData
     * @return mixed
     */
    public function createChecklist($aData)
    {
        //$oTask = TaskerTask::find($aData['task_id']);
        if (!$this->checkRights($aData['task_id'], 'edit')) {
            return ['message' => 'Не достаточно прав', 'status_code' => 403];
        }
        $aData['status'] = 0; //не выполнен
        $oChecklist      = TaskerChecklist::query()->create($aData);

        return ['id' => $oChecklist->getAttribute('id'), 'status_code' => 200];
    }

    /**
     * Обновить чек-лист в задаче
     * @param $aData
     * @return mixed
     */
    public function updateChecklist($aData)
    {
        $oTask = TaskerTask::find($aData['task_id']);
        if (!$this->checkRights($aData['id'], 'edit')) {
            return ['message' => 'Не достаточно прав', 'status_code' => 403];
        }
        $oChecklist = TaskerChecklist::find($aData['id']);
        if (is_null($oChecklist)) {
            return ['message' => 'Запись не найдена', 'status_code' => 404];
        }
        $oChecklist->update($aData);

        return ['id' => $oChecklist->id, 'status_code' => 200];
    }

    /**
     * Удалить задачу
     * @param $aData
     * @return mixed
     */
    public function deleteChecklist($aData)
    {
        if (!$this->checkRights($aData['task_id'], 'edit')) {
            return ['message' => 'Не достаточно прав', 'status_code' => 403];
        }
        $oChecklist = TaskerChecklist::whereId($aData['id'])->delete();

        return ['id' => $aData['id'], 'status_code' => 200];
    }

    /**
     * Изменение чек-листа
     * @param $aData
     * @return mixed
     */
    public function setChecklistTask($aData)
    {
        //$oTask = TaskerTask::query()->find($aData['task_id']);
        if (!$this->checkRights($aData['task_id'], 'edit')) {
            return ['message' => 'Недостаточно прав', 'status_code' => 403];
        }
        /** @var TaskerChecklist|null $oChecklist */
        $oChecklist = TaskerChecklist::query()->find($aData['id']);
        if ($oChecklist === null) {
            return ['message' => 'Запись не найдена', 'status_code' => 404];
        }
        $oChecklist->update(['status' => $aData['status']]);

        return ['id' => $oChecklist->id, 'status_code' => 200];
    }

    /**
     * Получение всех элементов чек-листа конкретной таски
     * @param array $data
     * @return array
     */
    public function getChecklistItems(array $data): array
    {
        // todo: здесь должна быть проверка на права доступа
        if (!empty($data['task_id'])) {
            $checklistItems = TaskerChecklist::query()->where('task_id', $data['task_id'])->orderBy('order')->get(['id', 'task_id', 'title', 'hint', 'status', 'order'])->toArray();

            return ['checklistItems' => $checklistItems, 'status_code' => 200];
        }

        return ['message' => 'Отсутствует task_id.', 'status_code' => 422];
    }
}
