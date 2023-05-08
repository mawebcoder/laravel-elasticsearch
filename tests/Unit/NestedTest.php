<?php

namespace Tests\Unit;

use Mawebcoder\Elasticsearch\Models\Elasticsearch;
use PHPUnit\Framework\TestCase;

class NestedTest extends TestCase
{

    public Elasticsearch $elasticsearch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->elasticsearch = new Elasticsearch();
    }

    public function test_can_detect_dot_nested_search()
    {
        $field = 'categories->name';

        $result = $this->elasticsearch->isNestedSearch($field);

        $this->assertTrue($result);
    }
}