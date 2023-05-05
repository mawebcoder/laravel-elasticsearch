<?php

namespace Mawebcoder\Elasticsearch\Trait;

trait Aggregatable
{
    /**
     * @param string $field
     * @param string|null $aggName
     * @return $this
     */
    public function sum(string $field, ?string $aggName = 'custom_aggregation'): static
    {
        $this->aggregate('sum', $field, $aggName);
        
        return $this;
    }

    /**
     * @param string $field
     * @param string|null $aggName
     * @return $this
     */
    public function min(string $field, ?string $aggName = 'custom_aggregation'): static
    {
        $this->aggregate('min', $field, $aggName);

        return $this;
    }

    /**
     * @param string $field
     * @param string|null $aggName
     * @return $this
     */
    public function max(string $field, ?string $aggName = 'custom_aggregation'): static
    {
        $this->aggregate('max', $field, $aggName);

        return $this;
    }

    /**
     * @param string $field
     * @param string|null $aggName
     * @return $this
     */
    public function average(string $field, ?string $aggName = 'custom_aggregation'): static
    {
        $this->aggregate('avg', $field, $aggName);

        return $this;
    }

    /**
     * Compute the count, min, max, avg, sum in one go
     * @param string $field
     * @param string|null $aggName
     * @return $this
     */
    public function stats(string $field, ?string $aggName = 'custom_aggregation'): static
    {
        $this->aggregate('stats', $field, $aggName);

        return $this;
    }


    /**
     * Computes the count of unique values for a given field.
     * @param string $field
     * @param string|null $aggName
     * @return $this
     */
    public function cardinality(string $field, ?string $aggName = 'custom_aggregation'): static
    {
        $this->aggregate('cardinality', $field, $aggName);

        return $this;
    }

    /**
     * @param string $operation
     * @param string $field
     * @param string $aggName
     * @return void
     */
    private function aggregate(string $operation, string $field, string $aggName): void
    {
        $this->search['aggs'][$aggName][$operation]['field'] = $field;
    }
}