<?php

/*
 * Symfony DataTables Bundle
 * (c) Omines Internetbureau B.V. - https://omines.nl/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Omines\DataTablesBundle;

use Omines\DataTablesBundle\Adapter\AdapterInterface;
use Omines\DataTablesBundle\Adapter\ResultSetInterface;
use Omines\DataTablesBundle\Column\AbstractColumn;
use Omines\DataTablesBundle\DependencyInjection\Instantiator;
use Omines\DataTablesBundle\Exception\InvalidArgumentException;
use Omines\DataTablesBundle\Exception\InvalidConfigurationException;
use Omines\DataTablesBundle\Exception\InvalidStateException;
use Omines\DataTablesBundle\Exporter\DataTableExporterManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * DataTable.
 *
 * @author Robbert Beesems <robbert.beesems@omines.com>
 */
class DataTable
{
    const DEFAULT_OPTIONS = [
        'jQueryUI' => false,
        'pagingType' => 'full_numbers',
        'lengthMenu' => [[10, 25, 50, -1], [10, 25, 50, 'All']],
        'pageLength' => 10,
        'displayStart' => 0,
        'serverSide' => true,
        'processing' => true,
        'paging' => true,
        'lengthChange' => true,
        'ordering' => true,
        'searching' => false,
        'search' => null,
        'searchCols' => [],
        'autoWidth' => false,
        'order' => [],
        'searchDelay' => 400,
        'dom' => 'lftrip',
        'orderCellsTop' => true,
        'stateSave' => false,
        'fixedHeader' => false,
    ];

    const DEFAULT_TEMPLATE = '@DataTables/datatable_html.html.twig';
    const SORT_ASCENDING = 'asc';
    const SORT_DESCENDING = 'desc';

    /** @var AdapterInterface */
    protected $adapter;

    /** @var AbstractColumn[] */
    protected $columns = [];

    /** @var array<string, AbstractColumn> */
    protected $columnsByName = [];

    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    /** @var DataTableExporterManager */
    protected $exporterManager;

    /** @var string */
    protected $method = Request::METHOD_POST;

    /** @var array */
    protected $options;

    /** @var bool */
    protected $languageFromCDN = true;

    /** @var string */
    protected $name = 'dt';

    /** @var string */
    protected $persistState = 'query';

    /** @var string */
    protected $template = self::DEFAULT_TEMPLATE;

    /** @var array */
    protected $templateParams = [];

    /** @var callable */
    protected $transformer;

    /** @var string */
    protected $translationDomain = 'messages';

    /** @var FormInterface */
    protected $filterForm;

    /** @var FormView */
    protected $filterView;

    /** @var DataTableRendererInterface */
    private $renderer;

    /** @var DataTableState */
    private $state;

    /** @var Instantiator */
    private $instantiator;

    /** @var bool */
    private $init;

    /**
     * DataTable constructor.
     */
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        DataTableExporterManager $exporterManager,
        FormFactoryInterface $formFactory,
        array $options = [],
        Instantiator $instantiator = null
    )
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->exporterManager = $exporterManager;

        $this->instantiator = $instantiator ?? new Instantiator();

        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $this->options = $resolver->resolve($options);

        $this->filterForm = $formFactory->create();
    }

    /**
     * @return $this
     */
    public function add(string $name, string $type, array $options = [])
    {
        // Ensure name is unique
        if (isset($this->columnsByName[$name])) {
            throw new InvalidArgumentException(sprintf("There already is a column with name '%s'", $name));
        }

        $column = $this->instantiator->getColumn($type);
        $column->initialize($name, count($this->columns), $options, $this);

        if ($column->isSearchable()) {
            $column->createFilter($this->filterForm);
        }

        $this->columns[] = $column;
        $this->columnsByName[$name] = $column;

        return $this;
    }

    public function getHtmlId(): string
    {
        return str_replace(".", "_", $this->getName());
    }

    public function getFilterHtmlId(): string
    {
        return $this->getHtmlId() . "-filter";
    }

    /**
     * Adds an event listener to an event on this DataTable.
     *
     * @param string   $eventName The name of the event to listen to
     * @param callable $listener  The listener to execute
     * @param int      $priority  The priority of the listener. Listeners
     *                            with a higher priority are called before
     *                            listeners with a lower priority.
     *
     * @return $this
     */
    public function addEventListener(string $eventName, callable $listener, int $priority = 0): self
    {
        $this->eventDispatcher->addListener($eventName, $listener, $priority);

        return $this;
    }

    /**
     * @param int|string|AbstractColumn $column
     * @return $this
     */
    public function addOrderBy($column, string $direction = self::SORT_ASCENDING)
    {
        if (!$column instanceof AbstractColumn) {
            $column = is_int($column) ? $this->getColumn($column) : $this->getColumnByName((string) $column);
        }
        $this->options['order'][] = [$column->getIndex(), $direction];

        return $this;
    }

    /**
     * @return $this
     */
    public function createAdapter(string $adapter, array $options = []): self
    {
        return $this->setAdapter($this->instantiator->getAdapter($adapter), $options);
    }

    public function getAdapter(): AdapterInterface
    {
        return $this->adapter;
    }

    /**
     * @return FormInterface
     */
    public function getFilterForm(): FormInterface
    {
        return $this->filterForm;
    }

    /**
     * @return FormView
     */
    public function getFilterView(): FormView
    {
        if ($this->filterView === null) {
            $this->filterView = $this->filterForm->createView();
        }

        return $this->filterView;
    }

    public function getColumn(int $index): AbstractColumn
    {
        if ($index < 0 || $index >= count($this->columns)) {
            throw new InvalidArgumentException(sprintf('There is no column with index %d', $index));
        }

        return $this->columns[$index];
    }

    public function getColumnByName(string $name): AbstractColumn
    {
        if (!isset($this->columnsByName[$name])) {
            throw new InvalidArgumentException(sprintf("There is no column named '%s'", $name));
        }

        return $this->columnsByName[$name];
    }

    /**
     * @return AbstractColumn[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    public function isLanguageFromCDN(): bool
    {
        return $this->languageFromCDN;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPersistState(): string
    {
        return $this->persistState;
    }

    /**
     * @return DataTableState|null
     */
    public function getState()
    {
        return $this->state;
    }

    public function getTranslationDomain(): string
    {
        return $this->translationDomain;
    }

    public function isCallback(): bool
    {
        return ($this->init || null === $this->state) ? false : $this->state->isCallback();
    }

    /**
     * @return $this
     */
    public function handleRequest(Request $request): self
    {
        if ($request->getMethod() === Request::METHOD_GET) {
            $parameters = $request->query;
            $this->init = true;
        } elseif($request->getMethod() === Request::METHOD_POST) {
            $parameters = $request->request;
            $this->init = false;
        } else {
            throw new InvalidConfigurationException(sprintf("Unknown request method '%s'", $this->getMethod()));
        }

        //if ($this->getName() === $parameters->get('_dt')) {
            if (null === $this->state) {
                $this->state = DataTableState::fromDefaults($this);
            }
            
            $this->state->applyParameters($parameters, $this->init);
        //}

        // set default filter values
        if ($this->init) {
            $filterData = [];
            foreach($this->state->getSearchColumns() as $columnName => $searchColumn) {
                $filterData[$columnName] = $searchColumn['search'];
            }

            $this->filterForm->setData($filterData);
        }

        return $this;
    }

    public function renderTemplate(): string
    {
        return $this->renderer->renderDataTable($this, $this->template, $this->templateParams);
    }

    public function getResponse(): Response
    {
        if (null === $this->state) {
            throw new InvalidStateException('The DataTable does not know its state yet, did you call handleRequest?');
        }

        // Server side export
        if (null !== $this->state->getExporterName()) {
            return $this->exporterManager
                ->setDataTable($this)
                ->setExporterName($this->state->getExporterName())
                ->getResponse();
        }

        $resultSet = $this->getResultSet();
        $response = [
            'draw' => $this->state->getDraw(),
            'recordsTotal' => $resultSet->getTotalRecords(),
            'recordsFiltered' => $resultSet->getTotalDisplayRecords(),
            'data' => iterator_to_array($resultSet->getData()),
        ];

        return new JsonResponse($response);
    }

    public function getInitialOptions(): array
    {
        $options = array_merge($this->getOptions(), [
            'columns' => array_map(
                static function (AbstractColumn $column) {
                    return [
                        'data' => $column->getName(),
                        'orderable' => $column->isOrderable(),
                        'searchable' => $column->isSearchable(),
                        'visible' => $column->isVisible(),
                        'className' => $column->getClassName(),
                    ];
                }, $this->getColumns()
            ),
        ]);

        // page length
        if ($this->state->getLength() !== null) {
            $options["pageLength"] = $this->state->getLength();
        }

        // filters
        $searchCols = [];

        foreach ($this->getColumns() as $column) {
            $searchCols[] = $this->state->getSearchColumns()[$column->getName()] ?? null;
        }

        $options["searchCols"] = $searchCols;

        // order
        $options["order"] = array_map(static function($order) {
            /** @var AbstractColumn $column */
            $column = $order[0];
            return [$column->getIndex(), $order[1]];
        }, $this->state->getOrderBy());

        return $options;
    }

    protected function getResultSet(): ResultSetInterface
    {
        if (null === $this->adapter) {
            throw new InvalidStateException('No adapter was configured yet to retrieve data with. Call "createAdapter" or "setAdapter" before attempting to return data');
        }

        return $this->adapter->getData($this->state);
    }

    /**
     * @return callable|null
     */
    public function getTransformer()
    {
        return $this->transformer;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param $name
     * @return mixed|null
     */
    public function getOption($name)
    {
        return $this->options[$name] ?? null;
    }

    /**
     * @return DataTable
     */
    public function setAdapter(AdapterInterface $adapter, array $options = null): self
    {
        if (null !== $options) {
            $adapter->configure($options);
        }
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * @return $this
     */
    public function setLanguageFromCDN(bool $languageFromCDN): self
    {
        $this->languageFromCDN = $languageFromCDN;

        return $this;
    }

    /**
     * @return $this
     */
    public function setMethod(string $method): self
    {
        $this->method = $method;

        return $this;
    }

    /**
     * @return $this
     */
    public function setPersistState(string $persistState): self
    {
        $this->persistState = $persistState;

        return $this;
    }

    /**
     * @return $this
     */
    public function setRenderer(DataTableRendererInterface $renderer): self
    {
        $this->renderer = $renderer;

        return $this;
    }

    /**
     * @return $this
     */
    public function setName(string $name): self
    {
        if (empty($name)) {
            throw new InvalidArgumentException('DataTable name cannot be empty');
        }
        $this->name = $name;

        return $this;
    }

    /**
     * @return $this
     */
    public function setTemplate(string $template, array $parameters = []): self
    {
        $this->template = $template;
        $this->templateParams = $parameters;

        return $this;
    }

    /**
     * @return $this
     */
    public function setTranslationDomain(string $translationDomain): self
    {
        $this->translationDomain = $translationDomain;

        return $this;
    }

    /**
     * @return $this
     */
    public function setTransformer(callable $formatter)
    {
        $this->transformer = $formatter;

        return $this;
    }

    public function hasFilters(): bool
    {
        return $this->filterForm->count() > 0;
    }

    /**
     * @return $this
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(self::DEFAULT_OPTIONS);

        return $this;
    }
}
