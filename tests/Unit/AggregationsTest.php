<?php

namespace Tests\Unit;

use Mawebcoder\Elasticsearch\Models\Elasticsearch;
use PHPUnit\Framework\TestCase;

class AggregationsTest extends TestCase
{

    public Elasticsearch $elasticsearch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->elasticsearch = new Elasticsearch();
    }

    public function test_bucket_aggregation()
    {
        $this->elasticsearch->bucket('name', 'any-name-number',1300);

        $expected = [
            'any-name-number' =>
                [
                    'terms' => [
                        'field' => 'name',
                        'size'=>1300
                    ]
                ]

        ];

        $this->assertSame($expected, $this->elasticsearch->search['aggs']);
    }
}