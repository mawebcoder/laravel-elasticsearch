<?php

namespace Mawebcoder\Elasticsearch\Bulk;

class BulkInsert
{
    public array $notImportedItems = [];
    public array $importedItems = [];

    public bool $hasError = false;

    public function setNotImportedItems(array $items): static
    {
        $this->notImportedItems = $items;
        return $this;
    }

    public function getNotImportedItems(): array
    {
        return $this->notImportedItems;
    }

    public function setImportedItems(array $items): static
    {
        $this->importedItems = $items;
        return $this;
    }

    public function getImportedItems(): array
    {
        return $this->importedItems;
    }

    public function setHasError(bool $hasError): static
    {
        $this->hasError = $hasError;
        return $this;
    }

    public function hasError(): bool
    {
        return $this->hasError;
    }
}