<?php

namespace Omines\DataTablesBundle\Column;

use Symfony\Component\OptionsResolver\OptionsResolver;

class ActionColumn extends TwigColumn
{
    public const TYPE_TOOLBAR = "toolbar";
    public const TYPE_DROPDOWN = "dropdown";

    protected function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver
            ->setDefault("type", self::TYPE_TOOLBAR)
            ->setDefault("orderable", false)
            ->setDefault("searchable", false)
            ->setDefault("template", "@DataTables/Column/ActionColumn/%s.html.twig")
            ->setRequired("items")
            ->setRequired("type")
            ->setAllowedTypes("items", ["array", "Closure"])
            ->setAllowedTypes("type", "string");

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function render($value, $context)
    {
        $items = $this->options["items"];

        if (is_callable($items)) {
            $items = $items($context);
        }

        if (empty($items)) {
            return "";
        }

        return $this->twig->render($this->getTemplate(), [
            "row" => $context,
            "value" => $value,
            "label" => $this->getLabel(),
            "items" => $items,
            "datatable" => $this->getDataTable(),
        ]);
    }

    public function getTemplate(): string
    {
        return sprintf($this->options["template"], $this->options["type"]);
    }
}
