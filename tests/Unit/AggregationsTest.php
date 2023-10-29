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

    public function test_bucket_aggregation_while_is_fields_are_string(): void
    {
        $this->elasticsearch->bucket('name', 'any-name-number', 1300);

        $expected = [
            'any-name-number' =>
                [
                    'terms' => [
                        'field' => 'name',
                        'size' => 1300,
                        'order' => [
                            '_key' => 'desc'
                        ]
                    ]
                ]

        ];
        $this->assertSame($expected, $this->elasticsearch->search['aggs']);
    }

    public function test_bucket_aggregation_with_key_sort(): void
    {
        $this->elasticsearch->bucket('name', 'any-name-number', 1300, 'name', 'asc');

        $expected = [
            'any-name-number' =>
                [
                    'terms' => [
                        'field' => 'name',
                        'size' => 1300,
                        'order' => [
                            'name' => 'asc'
                        ]
                    ]
                ]

        ];
        $this->assertSame($expected, $this->elasticsearch->search['aggs']);
    }

    public function test_bucket_aggregation_with_array()
    {
        $this->elasticsearch->bucket(['year', 'quarter'], 'any-name-number', 1300, 'year', 'desc');

        $expected = [
            'any-name-number' =>
                [
                    "multi_terms" => [
                        "terms" => [
                            [
                                'field' => 'year',
                            ],
                            [
                                'field' => 'quarter'
                            ]
                        ],
                        'order' => [
                            'year' => 'desc'
                        ]
                    ]
                ]

        ];
        $this->assertSame($expected, $this->elasticsearch->search['aggs']);
    }

    public function test_bucket_aggregation_with_array_without_order_key()
    {
        $this->elasticsearch->bucket(['year', 'quarter'], 'any-name-number', 1300);

        $expected = [
            'any-name-number' =>
                [
                    "multi_terms" => [
                        "terms" => [
                            [
                                'field' => 'year',
                            ],
                            [
                                'field' => 'quarter'
                            ]
                        ],
                        'order' => [
                            '_key' => 'desc'
                        ]
                    ]
                ]

        ];
        $this->assertSame($expected, $this->elasticsearch->search['aggs']);
    }
}