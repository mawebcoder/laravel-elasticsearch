<?php

namespace Tests\Unit;

use Throwable;
use PHPUnit\Framework\TestCase;
use Tests\DummyRequirements\Models\EUserModel;

class ConditionsOperatorTest extends TestCase
{
    public EUserModel $elasticsearch;

    protected function setUp(): void
    {
        $this->elasticsearch = new EUserModel();
    }

    /**
     * @throws Throwable
     */
    public function test_operator_in_where_while_two_arg_given():void
    {
        $this->elasticsearch->where('name', false);

        $arr = [
            "query" => [
                "bool" => [
                    "should" => [
                        0 => [
                            "bool" => [
                                "must" => [
                                    0 => [
                                        "term" => [
                                            "name" => [
                                                "value" => false
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "_source" => []
        ];

        $this->assertEquals($arr, $this->elasticsearch->search);
    }

    /**
     * @throws Throwable
     */
    public function test_where_id_can_be_replaced_with_elastic_id():void
    {
        $this->elasticsearch->where('id', false);

        $expected = [
            "query" => [
                "bool" => [
                    "should" => [
                        0 => [
                            "bool" => [
                                "must" => [
                                    0 => [
                                        "term" => [
                                            "_id" => [
                                                "value" => false
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "_source" => []
        ];

        $this->assertEquals($expected,$this->elasticsearch->search);
    }


    /**
     * @throws Throwable
     */
    public function test_operator_in_where_while_three_arg_given():void
    {
        $this->elasticsearch->where('name', '<>', false);

        $arr = [
            "query" => [
                "bool" => [
                    "should" => [
                        0 => [
                            "bool" => [
                                "must" => [
                                    [
                                        "bool" => [
                                            "must_not" => [
                                                [
                                                    "term" => [
                                                        'name' => [
                                                            'value' => false
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "_source" => []
        ];

        $this->assertEquals($arr, $this->elasticsearch->search);
    }

    public function test_operator_in_orWhere_while_two_argument():void
    {
        $this->elasticsearch->orWhere('name', false);

        $arr = [
            "query" => [
                "bool" => [
                    "should" => [
                        0 => [
                            "bool" => [
                                "must" => []
                            ]
                        ],
                        1 => [
                            "term" => [
                                "name" => [
                                    "value" => false
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "_source" => []
        ];

        $this->assertEquals($arr, $this->elasticsearch->search);
    }

    public function test_operator_in_orWhere_while_three_argument():void
    {
        $this->elasticsearch->orWhere('name', 'like', false);

        $arr = [
            "query" => [
                "bool" => [
                    "should" => [
                        0 => [
                            "bool" => [
                                "must" => []
                            ]
                        ],
                        1 => [
                            "match_phrase_prefix" => [
                                "name" => [
                                    "query" => false
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "_source" => []
        ];

        $this->assertSame($arr, $this->elasticsearch->search);
    }

    public function test_operator_in_whereTerm_with_two_argument():void
    {
        $this->elasticsearch->whereTerm('name', false);

        $arr = [
            "query" => [
                "bool" => [
                    "should" => [
                        0 => [
                            "bool" => [
                                "must" => [
                                    0 => [
                                        "match" => [
                                            "name" => [
                                                "query" => false
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "_source" => []
        ];

        $this->assertSame($arr, $this->elasticsearch->search);
    }

    public function test_operator_in_whereTerm_with_three_argument():void
    {
        $this->elasticsearch->whereTerm('name', '<>', false);

        $arr = [
            "query" => [
                "bool" => [
                    "should" => [
                        0 => [
                            "bool" => [
                                "must" => [
                                    0 => [
                                        "bool" => [
                                            "must_not" => [
                                                0 => [
                                                    "match" => [
                                                        "name" => [
                                                            "query" => false
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "_source" => []
        ];

        $this->assertSame($arr, $this->elasticsearch->search);
    }

    /**
     * @throws Throwable
     */
    public function test_operator_in_orWhereTerm(): void
    {
        $this->elasticsearch->orWhereTerm('name', false);

        $arr = [
            "query" => [
                "bool" => [
                    "should" => [
                        0 => [
                            "bool" => [
                                "must" => []
                            ]
                        ],
                        1 => [
                            "match" => [
                                "name" => [
                                    "query" => false
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "_source" => []
        ];

        $this->assertSame($arr, $this->elasticsearch->search);
    }

    public function test_operator_in_orWhereTerm_with_three_argument():void
    {
        $this->elasticsearch->orWhereTerm('name', '<>', false);


        $arr = [
            "query" => [
                "bool" => [
                    "should" => [
                        0 => [
                            "bool" => [
                                "must" => []
                            ]
                        ],
                        1 => [
                            "bool" => [
                                "must_not" => [
                                    "match" => [
                                        "name" => [
                                            "query" => false
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "_source" => []
        ];

        $this->assertSame($arr, $this->elasticsearch->search);
    }

}