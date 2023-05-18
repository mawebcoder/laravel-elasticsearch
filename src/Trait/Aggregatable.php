<?php

namespace Mawebcoder\Elasticsearch\Trait;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;
use Mawebcoder\Elasticsearch\Exceptions\InvalidInterval;
use Mawebcoder\Elasticsearch\Exceptions\InvalidIntervalType;
use Mawebcoder\Elasticsearch\Exceptions\InvalidRanges;
use Mawebcoder\Elasticsearch\Exceptions\InvalidSortDirection;
use Mawebcoder\Elasticsearch\Facade\Elasticsearch;
use ReflectionException;

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
     * @throws InvalidInterval
     */
    public function dateHistogram(
        string $field,
        string $intervalType,
        string $interval,
        string $aggName = 'custom_aggregation',
        ?string $order = null
    ): static {
        $this->checkIntervalType($intervalType);

        $this->validateInterval($intervalType, $interval);

        $this->aggregate(
            operation: 'date_histogram',
            field: $field,
            aggName: $aggName,
            intervalType: $intervalType,
            interval: $interval,
            order: $order
        );

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
    public function histogram(
        string $field,
        int $interval,
        string $aggName = 'custom_aggregation',
        ?string $order = null
    ): static {
        $this->aggregate(
            operation: 'histogram',
            field: $field,
            aggName: $aggName,
            intervalType: 'interval',
            interval: $interval,
            order: $order
        );

        return $this;
    }

    /**
     * @param string $field
     * @param int[] $ranges
     * @param string $aggName
     * @return $this
     * @throws InvalidSortDirection
     * @throws InvalidRanges
     */
    public function range(string $field, array $ranges, string $aggName = 'custom_aggregation'): static
    {
        $this->validateRanges($ranges);

        $this->aggregate('range', $field, $aggName, ranges: $ranges);

        return $this;
    }

    /**
     * @param string $field
     * @param int $size
     * @param string $aggName
     * @return void
     * @throws InvalidSortDirection
     */
    public function terms(string $field, int $size = 5, string $aggName = 'custom_aggregation'): void
    {
        $this->aggregate('terms', $field, $aggName, size: $size);
    }

    /**
     * @param string $intervalType
     * @return void
     * @throws InvalidIntervalType
     */
    private function checkIntervalType(string $intervalType): void
    {
        if (!$intervalType || in_array($intervalType, ['fixed_interval', 'calendar_interval'])) {
            return;
        }
        throw new InvalidIntervalType(
            message: 'date histogram interval must be either fixed_interval or calendar_interval.'
        );
    }

    /**
     * @param string $operation
     * @param string $field
     * @param string $aggName
     * @param string|null $intervalType
     * @param string|int|null $interval
     * @param string|null $order
     * @param array|null $ranges
     * @param int|null $size
     * @return void
     * @throws InvalidSortDirection
     */
    private function aggregate(
        string $operation,
        string $field,
        string $aggName,
        string $intervalType = null,
        string|int $interval = null,
        string $order = null,
        array $ranges = null,
        int $size = null
    ): void {
        $this->search['aggs'][$aggName][$operation]['field'] = $field;

        $this->setInterval($operation, $aggName, $intervalType, $interval);

        $this->checkSortOrder($order);

        $this->setSortOrder($operation, $aggName, $order);

        $this->setRanges($operation, $aggName, $ranges);

        $this->setSize($operation, $aggName, $size);
    }

    /**
     * @param string $operation
     * @param string $aggName
     * @param string|null $intervalType
     * @param string|int|null $interval
     * @return void
     */
    private function setInterval(
        string $operation,
        string $aggName,
        ?string $intervalType,
        string|int|null $interval
    ): void {
        if (!$intervalType || !$interval) {
            return;
        }

        $this->search['aggs'][$aggName][$operation][$intervalType] = $interval;
    }

    /**
     * @param string|null $order
     * @return void
     * @throws InvalidSortDirection
     */
    private function checkSortOrder(?string $order): void
    {
        if (!$order || in_array($order, ['asc', 'desc'])) {
            return;
        }
        throw new InvalidSortDirection(message: 'order direction must be either asc or desc.');
    }

    /**
     * @param string $operation
     * @param string $aggName
     * @param string|null $order
     * @return void
     */
    private function setSortOrder(string $operation, string $aggName, ?string $order): void
    {
        if (!in_array($order, ['asc', 'desc'])) {
            return;
        }

        $this->search['aggs'][$aggName][$operation]['order'] = ['_key' => $order];
    }

    /**
     * @param string $type
     * @param string $interval
     * @return void
     * @throws InvalidInterval
     */
    private function validateInterval(string $type, string $interval): void
    {
        ($type === 'calendar_interval') ?
            $this->calendarIntervalIsValid($interval)
            :
            $this->fixedIntervalIsValid($interval);
    }


    /**
     * @param string $interval
     * @return void
     * @throws InvalidInterval
     */
    private function calendarIntervalIsValid(string $interval): void
    {
        $calendarInterval = '/^(\d+)([mhdwMqy])$/';

        if (preg_match($calendarInterval, $interval)) {
            return;
        }

        throw new InvalidInterval(
            message: 'calendar interval must be a combination of digits and one of these letters: m,h,d,w,M,q,y.'
        );
    }

    /**
     * @param string $interval
     * @return void
     * @throws InvalidInterval
     */
    private function fixedIntervalIsValid(string $interval): void
    {
        $fixedInterval = '/^(\d+)(ms|s|m|h|d)$/';

        if (preg_match($fixedInterval, $interval)) {
            return;
        }

        throw new InvalidInterval(
            message: 'fixed interval must be a combination of digits and one of these letters: ms,s,m,h,d.'
        );
    }

    /**
     * @param string $operation
     * @param string $aggName
     * @param array|null $ranges
     * @return void
     */
    private function setRanges(string $operation, string $aggName, ?array $ranges): void
    {
        if (!$ranges) {
            return;
        }

        $this->search['aggs'][$aggName][$operation]['ranges'] = $ranges;
    }

    /**
     * @param array $ranges
     * @return void
     * @throws InvalidRanges
     */
    private function validateRanges(array $ranges): void
    {
        foreach ($ranges as $range) {
            $fromCount = 0;
            $toCount = 0;
            $keys = array_keys($range);
            $allowedKeys = ['from', 'to'];

            if (count(array_diff($keys, $allowedKeys)) > 0) {
                throw new InvalidRanges(message: 'ranges must contain either a single "from", a single "to", or both.');
            }

            if (!isset($range['from']) && !isset($range['to'])) {
                throw new InvalidRanges(message: 'ranges must contain either a single "from", a single "to", or both.');
            }

            if (isset($range['from'])) {
                if (!is_int($range['from'])) {
                    throw new InvalidRanges(message: 'ranges must contain integer values only.');
                }
                $fromCount++;
            }

            if (isset($range['to'])) {
                if (!is_int($range['to'])) {
                    throw new InvalidRanges(message: 'ranges must contain integer values only.');
                }
                $toCount++;
            }

            if (!($fromCount <= 1 && $toCount <= 1)) {
                throw new InvalidRanges(message: 'ranges must contain either a single "from", a single "to", or both.');
            }
        }
    }

    /**
     * @param string $operation
     * @param string $aggName
     * @param int|null $size
     * @return void
     */
    private function setSize(string $operation, string $aggName, ?int $size): void
    {
        if (!$size) {
            return;
        }

        $this->search['aggs'][$aggName][$operation]['size'] = $size;
    }

    /**
     * @return mixed
     * @throws GuzzleException
     * @throws ReflectionException
     */
    public function count(): int
    {
        $search = Arr::except($this->search, ['_source', 'sort']);
        $result = Elasticsearch::setModel(static::class)
            ->post('_count', $search);

        return json_decode($result->getBody(), true)['count'];
    }

    public function bucket(string $field, string $as, int $size = 2147483647): static
    {
        $this->search['aggs'][$as]['terms'] = ['field' => $field, 'size' => $size];

        return $this;
    }
}