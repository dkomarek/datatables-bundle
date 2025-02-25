<?php

/*
 * Symfony DataTables Bundle
 * (c) Omines Internetbureau B.V. - https://omines.nl/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Omines\DataTablesBundle\Column;

use Omines\DataTablesBundle\Adapter\Api\Query;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\Filter\AbstractFilter;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * AbstractColumn.
 *
 * @author Niels Keurentjes <niels.keurentjes@omines.com>
 */
abstract class AbstractColumn
{
    /** @var array<string, OptionsResolver> */
    private static $resolversByClass = [];

    /** @var string */
    private $name;

    /** @var int */
    private $index;

    /** @var DataTable */
    private $dataTable;

    /** @var array<string, mixed> */
    protected $options;

    public function initialize(string $name, int $index, array $options, DataTable $dataTable)
    {
        $this->name = $name;
        $this->index = $index;
        $this->dataTable = $dataTable;

        $class = get_class($this);
        if (!isset(self::$resolversByClass[$class])) {
            self::$resolversByClass[$class] = new OptionsResolver();
            $this->configureOptions(self::$resolversByClass[$class]);
        }
        $this->options = self::$resolversByClass[$class]->resolve($options);
    }

    /**
     * The transform function is responsible for converting column-appropriate input to a datatables-usable type.
     *
     * @param mixed|null $value The single value of the column, if mapping makes it possible to derive one
     * @param mixed|null $context All relevant data of the entire row
     * @return mixed
     */
    public function transform($value = null, $context = null)
    {
        $data = $this->getData();
        if (is_callable($data)) {
            $value = call_user_func($data, $context, $value);
        } elseif (null === $value) {
            $value = $data;
        }

        return $this->render($this->normalize($value), $context);
    }

    /**
     * Apply final modifications before rendering to result.
     *
     * @param mixed $value
     * @param mixed $context All relevant data of the entire row
     * @return mixed|string
     */
    protected function render($value, $context)
    {
        if (is_string($render = $this->options['render'])) {
            return sprintf($render, $value);
        } elseif (is_callable($render)) {
            return call_user_func($render, $value, $context);
        }

        return $value;
    }

    /**
     * The normalize function is responsible for converting parsed and processed data to a datatables-appropriate type.
     *
     * @param mixed $value The single value of the column
     * @return mixed
     */
    abstract public function normalize($value);

    /**
     * @return $this
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefaults([
                'label' => null,
                'labelTrans' => true,
                'data' => null,
                'field' => null,
                'propertyPath' => null,
                'visible' => true,
                'orderable' => null,
                'orderField' => null,
                'searchable' => null,
                'filterType' => null,
                'filterField' => null,
                'filterCallback' => null,
                'filterOptions' => [],
                'globalSearchable' => null,
                'filter' => null,
                'className' => null,
                'render' => null,
                'leftExpr' => null,
                'operator' => '=',
                'rightExpr' => null,
            ])
            ->setAllowedTypes('label', ['null', 'string'])
            ->setAllowedTypes('labelTrans', ['null', 'bool'])
            ->setAllowedTypes('data', ['null', 'string', 'callable'])
            ->setAllowedTypes('field', ['null', 'string'])
            ->setAllowedTypes('propertyPath', ['null', 'string'])
            ->setAllowedTypes('visible', 'boolean')
            ->setAllowedTypes('orderable', ['null', 'boolean'])
            ->setAllowedTypes('orderField', ['null', 'string'])
            ->setAllowedTypes('searchable', ['null', 'boolean'])
            ->setAllowedTypes('filterType', ['null', 'string'])
            ->setAllowedTypes('filterField', ['null', 'string'])
            ->setAllowedTypes('filterCallback', ['null', 'callable'])
            ->setAllowedTypes('filterOptions', ['array'])
            ->setAllowedTypes('globalSearchable', ['null', 'boolean'])
            ->setAllowedTypes('filter', ['null', AbstractFilter::class])
            ->setAllowedTypes('className', ['null', 'string'])
            ->setAllowedTypes('render', ['null', 'string', 'callable'])
            ->setAllowedTypes('operator', ['string'])
            ->setAllowedTypes('leftExpr', ['null', 'string', 'callable'])
            ->setAllowedTypes('rightExpr', ['null', 'string', 'callable'])
        ;

        return $this;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string|null
     */
    public function getLabel()
    {
        return $this->options['label'] ?? "{$this->dataTable->getName()}.columns.{$this->getName()}";
    }

    /**
     * @return bool
     */
    public function isLabelTranslatable(): bool
    {
        return $this->options['labelTrans'] ?? false;
    }

    /**
     * @return string|null
     */
    public function getField()
    {
        return $this->options['field'];
    }

    /**
     * @return string|null
     */
    public function getPropertyPath()
    {
        return $this->options['propertyPath'];
    }

    /**
     * @return callable|string|null
     */
    public function getData()
    {
        return $this->options['data'];
    }

    public function isVisible(): bool
    {
        return $this->options['visible'];
    }

    public function isSearchable(): bool
    {
        return $this->options['searchable'] ?? !empty($this->getField());
    }

    public function isOrderable(): bool
    {
        return $this->options['orderable'] ?? !empty($this->getOrderField());
    }

    /**
     * @return AbstractFilter
     */
    public function getFilter()
    {
        return $this->options['filter'];
    }

    /**
     * @return string
     */
    public function getFilterType()
    {
        return $this->options['filterType'];
    }

    /**
     * @return string
     */
    public function getFilterField()
    {
        return $this->options['filterField'] ?? $this->getField();
    }

    /**
     * @param Query $query
     * @return bool
     */
    public function filterCallback(Query $query, string $value): bool
    {
        $callback = $this->options['filterCallback'];
        if ($callback === null) {
            return false;
        }

        if (is_callable($callback)) {
            call_user_func($callback, $query, $value);
            return true;
        }

        return false;
    }

    /**
     * @return array
     */
    public function getFilterOptions(): array
    {
        return $this->options['filterOptions'];
    }

    /**
     * @return string|null
     */
    public function getOrderField()
    {
        return $this->options['orderField'] ?? $this->getField();
    }

    public function isGlobalSearchable(): bool
    {
        return $this->options['globalSearchable'] ?? $this->isSearchable();
    }

    /**
     * @return string
     */
    public function getLeftExpr()
    {
        $leftExpr = $this->options['leftExpr'];
        if (null === $leftExpr) {
            return $this->getField();
        }
        if (is_callable($leftExpr)) {
            return call_user_func($leftExpr, $this->getField());
        }

        return $leftExpr;
    }

    /**
     * @return mixed
     */
    public function getRightExpr($value)
    {
        $rightExpr = $this->options['rightExpr'];
        if (null === $rightExpr) {
            return $value;
        }
        if (is_callable($rightExpr)) {
            return call_user_func($rightExpr, $value);
        }

        return $rightExpr;
    }

    /**
     * @return string
     */
    public function getOperator()
    {
        return $this->options['operator'];
    }

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->options['className'];
    }

    public function getDataTable(): DataTable
    {
        return $this->dataTable;
    }

    /**
     * @param mixed $value
     * @return $this
     */
    public function setOption(string $name, $value): self
    {
        $this->options[$name] = $value;

        return $this;
    }

    /**
     * @param string $value
     * @return bool
     */
    public function isValidForSearch($value)
    {
        return true;
    }

    /**
     * @param FormInterface $filterForm
     * @return $this
     */
    public function createFilter(FormInterface $filterForm): self
    {
        $filterType = $this->getFilterType() ?? TextType::class;

        $options = array_merge([
            "label" => $this->getLabel(),
            "required" => false,
            "attr" => [
                "class" => "form-control-solid",
            ]
        ], $this->getFilterOptions());

        $options["attr"]["data-filter-index"] = $this->getIndex();

        $filterForm->add($this->getName(), $filterType, $options);

        return $this;
    }
}
