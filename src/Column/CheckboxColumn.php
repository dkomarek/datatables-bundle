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

use Symfony\Component\OptionsResolver\OptionsResolver;

class CheckboxColumn extends AbstractColumn
{
    private const DEFAULT_CHECKROW_TEMPLATE = '<div class="px-2 form-check form-check-sm form-check-custom form-check-solid"><input class="form-check-input datatable-check-row" type="checkbox" value="%value%"></div>';
    private const DEFAULT_CHECKALL_TEMPLATE = '<div class="px-2 form-check form-check-sm form-check-custom form-check-solid"><input class="form-check-input datatable-check-all" type="checkbox" data-kt-check="true" data-kt-check-target="#%datatable_id% .datatable-check-row"></div>';

    /**
     * {@inheritdoc}
     */
    protected function render($value, $context)
    {
        return str_replace("%value%", (string)$value, $this->getTemplate());
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($value)
    {
        return $value;
    }

    public function getLabel()
    {
        return str_replace("%datatable_id%", $this->getDataTable()->getHtmlId(), $this->options['label']);
    }

    /**
     * {@inheritdoc}
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver
            ->setRequired('template')
            ->setDefault('orderable', false)
            ->setDefault('searchable', false)
            ->setDefault('template', self::DEFAULT_CHECKROW_TEMPLATE)
            ->setDefault('label', self::DEFAULT_CHECKALL_TEMPLATE)
            ->setDefault('labelTrans', false)
            ->setAllowedTypes('template', 'string')
        ;

        return $this;
    }

    public function getTemplate(): string
    {
        return $this->options['template'];
    }
}
