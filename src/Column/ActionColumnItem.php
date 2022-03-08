<?php

namespace Omines\DataTablesBundle\Column;

class ActionColumnItem
{
    private string $key;
    private ?string $path;
    private ?string $icon;
    private ?string $label = null;
    private ?string $contextClass = null;

    public function __construct(string $key, string $path = null, string $icon = null)
    {
        $this->key = $key;
        $this->path = $path;
        $this->icon = $icon;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @param string $key
     * @return self
     */
    public function setKey(string $key): self
    {
        $this->key = $key;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * @param string|null $path
     * @return self
     */
    public function setPath(?string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLabel(): ?string
    {
        return $this->label;
    }

    /**
     * @param string|null $label
     * @return self
     */
    public function setLabel(?string $label): self
    {
        $this->label = $label;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * @param string|null $icon
     * @return self
     */
    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getContextClass(): ?string
    {
        return $this->contextClass;
    }

    /**
     * @param string|null $contextClass
     * @return self
     */
    public function setContextClass(?string $contextClass): self
    {
        $this->contextClass = $contextClass;
        return $this;
    }
}
