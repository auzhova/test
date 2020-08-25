<?php

namespace App\Services;

interface SearchAdsInterface
{

    public function __construct();


    /**
     * Фильтрация данных
     * @param $filters
     * @param  $bounds
     * @return object
     */
   public function filter($filters, $bounds);

    /**
     * Получение данных для вывода
     * @param $query
     * @param int $rLimit
     * @param int $rOffset
     * @param int $rPagination
     * @return array
     */
   public function getList($query, $rLimit = 0, $rOffset = 0, $rPagination = 0);
}