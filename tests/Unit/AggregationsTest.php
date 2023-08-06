<?php

namespace Tests\Unit;


use PHPUnit\Framework\TestCase;
use Tests\DummyRequirements\Models\EUserModel;

class AggregationsTest extends TestCase
{

    public EUserModel $elasticsearch;

    protected function setUp(): void
    {
        $this->elasticsearch = new EUserModel();
    }

    public function test_bucket_aggregation()
    {
        $this->elasticsearch->bucket('name', 'any-name-number', 1300);

        $expected = [
            'any-name-number' =>
                [
                    'terms' => [
                        'field' => 'name',
                        'size' => 1300
                    ]
                ]

        ];

        $this->assertSame($expected, $this->elasticsearch->search['aggs']);
    }
}