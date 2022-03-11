<?php

declare(strict_types=1);

namespace Omines\DataTablesBundle\Adapter\Api;

use Omines\DataTablesBundle\Adapter\AbstractAdapter;
use Omines\DataTablesBundle\Adapter\AdapterQuery;
use Omines\DataTablesBundle\Column\AbstractColumn;
use Omines\DataTablesBundle\DataTableState;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ApiAdapter extends AbstractAdapter
{
    private ApiClientInterface $client;
    private array $queryProcessors;

    /**
     * {@inheritdoc}
     */
    public function configure(array $options)
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $options = $resolver->resolve($options);

        $this->client = $options["client"];
        $this->queryProcessors = [$options["query"]];

    }

    protected function prepareQuery(AdapterQuery $query)
    {
        foreach ($query->getState()->getDataTable()->getColumns() as $column) {
            if (null === $column->getField()) {
                $column->setOption("field", $column->getName());
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function mapPropertyPath(AdapterQuery $query, AbstractColumn $column)
    {
        return $column->getField();
    }

    protected function getResults(AdapterQuery $query): \Traversable
    {
        $state = $query->getState();
        $listQuery = $this->buildQuery($state);

        $resultSet = $this->client->list($listQuery);
        $query->setTotalRows($resultSet->getTotalRecords());
        $query->setFilteredRows($resultSet->getTotalRecords());

        foreach ($resultSet->getData() as $item) {
            yield $item;
        }
    }

    protected function createQuery(): Query
    {
        return new Query();
    }

    protected function buildQuery(DataTableState $state): Query
    {
        $query = $this->createQuery();

        // default filters
        foreach($this->queryProcessors as $queryProcessor) {
            $queryProcessor($query);
        }

        // globalni filtr
        if (!empty($globalSearch = $state->getGlobalSearch())) {
            foreach ($state->getDataTable()->getColumns() as $column) {
                if ($column->isGlobalSearchable()) {
                    $query->addFilter($column->getField(), $globalSearch);
                }
            }
        }

        // konkretni filtry pro pole
        foreach ($state->getSearchColumns() as $searchInfo) {
            /** @var AbstractColumn $column */
            $column = $searchInfo["column"];
            $search = $searchInfo["search"];

            if ('' !== trim($search)) {
                if (null !== ($filter = $column->getFilter())) {
                    if (!$filter->isValidValue($search)) {
                        continue;
                    }
                }

                $query->addFilter($column->getField(), $search);
            }
        }

        // razeni
        foreach ($state->getOrderBy() as [$column, $direction]) {
            if ($column->isOrderable() && $orderField = $column->getOrderField()) {
                $query->addOrderBy($orderField, $direction);
            }
        }

        // strankovani
        if ($state->getLength() > 0) {
            $query->setItemsPerPage($state->getLength());

            if ($state->getStart() > 0) {
                $query->setPage((int)($state->getStart() / $state->getLength()) + 1);
            }
        }

        return $query;
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                "client" => [],
                "query" => static function(Query $query) {},
            ])
            ->setRequired(["client"])
            ->setAllowedTypes("client", ApiClientInterface::class)
            ->setAllowedTypes("query", "callable");
        ;
    }
}
