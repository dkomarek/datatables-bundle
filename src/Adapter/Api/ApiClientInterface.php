<?php

namespace Omines\DataTablesBundle\Adapter\Api;

use Omines\DataTablesBundle\Adapter\ResultSetInterface;

interface ApiClientInterface
{
    /**
     * Hledani vysledku
     * @param Query|null $query
     * @return ResultSetInterface
     */
    public function list(?Query $query = null): ResultSetInterface;
}
