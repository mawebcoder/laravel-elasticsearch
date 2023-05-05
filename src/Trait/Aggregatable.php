<?php

namespace Mawebcoder\Elasticsearch\Trait;

use Mawebcoder\Elasticsearch\Exceptions\InvalidIntervalType;
use Mawebcoder\Elasticsearch\Exceptions\InvalidSortDirection;

trait Aggregatable
{
    /**
     * @param string $field
     * @param string $aggName
     * @return $this
     * @throws InvalidSortDirection
     */
    public function sum(string $field, string $aggName = 'custom_aggregation'): static
    {
        $this->aggregate('sum', $field, $aggName);
        
        return $this;
    }

    /**
     * @param string $field
     * @param string $aggName
     * @return $this
     * @throws InvalidSortDirection
     */
    public function min(string $field, string $aggName = 'custom_aggregation'): static
    {
        $this->aggregate('min', $field, $aggName);

        return $this;
    }

    /**
     * @param string $field
     * @param string $aggName
     * @return $this
     * @throws InvalidSortDirection
     */
    public function max(string $field, string $aggName = 'custom_aggregation'): static
    {
        $this->aggregate('max', $field, $aggName);

        return $this;
    }

    /**
     * @param string $field
     * @param string $aggName
     * @return $this
     * @throws InvalidSortDirection
     */
    public function average(string $field, string $aggName = 'custom_aggregation'): static
    {
        $this->aggregate('avg', $field, $aggName);

        return $this;
    }

    /**
     * Compute the count, min, max, avg, sum in one go
     * @param string $field
     * @param string $aggName
     * @return $this
     * @throws InvalidSortDirection
     */
    public function stats(string $field, string $aggName = 'custom_aggregation'): static
    {
        $this->aggregate('stats', $field, $aggName);

        return $this;
    }


    /**
     * Computes the count of unique values for a given field.
     * @param string $field
     * @param string $aggName
     * @return $this
     * @throws InvalidSortDirection
     */
    public function cardinality(string $field, string $aggName = 'custom_aggregation'): static
    {
        $this->aggregate('cardinality', $field, $aggName);

        return $this;
    }

    /**
     * @param string $field
     * @param string $intervalType
     * @param string $interval
     * @param string $aggName
     * @param string|null $order
     * @return $this
     * @throws InvalidIntervalType
     * @throws InvalidSortDirection
     */
    public function dateHistogram(string $field, string $intervalType, string $interval, string $aggName = 'custom_aggregation',  ?string $order = null): static
    {
        if ($intervalType && !in_array($intervalType, ['fixed_interval', 'calendar_interval']))
        {
            throw new InvalidIntervalType(message: 'date histogram interval must be either fixed_interval or calendar_interval.');
        }

        $this->aggregate('date_histogram', $field, $aggName, $intervalType, $interval, $order);

        return $this;
    }

    /**
     * @param string $field
     * @param int $interval
     * @param string $aggName
     * @param string|null $order
     * @return $this
     * @throws InvalidSortDirection
     */
    public function histogram(string $field, int $interval, string $aggName = 'custom_aggregation', ?string $order = null): static
    {
        $this->aggregate('histogram', $field, $aggName, 'interval', $interval, $order);

        return $this;
    }

    /**
     * @param string $operation
     * @param string $field
     * @param string $aggName
     * @param string|null $intervalType
     * @param string|int|null $interval
     * @param string|null $order
     * @return void
     * @throws InvalidSortDirection
     */
    private function aggregate(
        string          $operation,
        string          $field,
        string          $aggName,
        ?string         $intervalType = null,
        string|int|null $interval = null,
        ?string         $order = null
    ): void
    {
        $this->search['aggs'][$aggName][$operation]['field'] = $field;

        if ($intervalType && $interval)
        {
            $this->search['aggs'][$aggName][$operation][$intervalType] = $interval;
        }

        if ($order && !in_array($order, ['asc', 'desc']))
        {
            throw new InvalidSortDirection(message: 'order direction must be either asc or desc.');
        }

        if (in_array($order, ['asc', 'desc']))
        {
            $this->search['aggs'][$aggName][$operation]['order'] = ['_key' => $order];
        }

    }
}