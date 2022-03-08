<?php

namespace Omines\DataTablesBundle\Adapter\Api;

class Query
{
    private array $data;

    public function addFilter(string $field, string $value): self
    {
        $this->data[$field] = $value;
        return $this;
    }

    public function addOrderBy(string $field, string $direction): self
    {
        $this->data["order[{$field}]"] = $direction;
        return $this;
    }

    public function setItemsPerPage(?int $itemsPerPage): self
    {
        $this->data["itemsPerPage"] = $itemsPerPage;
        return $this;
    }

    public function setPage(?int $page): self
    {
        $this->data["page"] = $page;
        return $this;
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
