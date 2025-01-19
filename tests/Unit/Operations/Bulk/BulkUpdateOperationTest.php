<?php

declare(strict_types=1);

namespace JeroenG\Explorer\Tests\Unit\Operations\Bulk;

use JeroenG\Explorer\Application\Operations\Bulk\BulkUpdateOperation;
use JeroenG\Explorer\Tests\Support\Models\TestModelWithoutSettings;
use JeroenG\Explorer\Tests\Support\Models\TestModelWithIndexed;
use JeroenG\Explorer\Tests\Support\Models\TestModelWithPrepare;
use JeroenG\Explorer\Tests\Support\Models\TestModelWithSettings;
use PHPUnit\Framework\TestCase;

class BulkUpdateOperationTest extends TestCase
{
    public function test_it_builds_with_an_empty_command(): void
    {
        $operation = new BulkUpdateOperation(':searchable_as:');
        self::assertEquals([], $operation->build());
    }

    public function test_it_builds_with_a_model_command(): void
    {
        $operation = new BulkUpdateOperation(':searchable_as:');
        $operation->add(new TestModelWithoutSettings());
        self::assertEquals([
            ['index' => [ '_index' => ':searchable_as:', '_id' => ':scout_key:' ]],
            [ 'data' => true ]
        ], $operation->build());
    }

    public function test_it_builds_with_multiple_model_command(): void
    {
        $operation = new BulkUpdateOperation(':searchable_as:');

        $operation->add(new TestModelWithoutSettings());
        $operation->add(new TestModelWithSettings());

        self::assertEquals([
            ['index' => [ '_index' => ':searchable_as:', '_id' => ':scout_key:' ]],
            [ 'data' => true ],

            ['index' => [ '_index' => ':searchable_as:', '_id' => ':scout_key:' ]],
            [ 'data' => true ]
        ], $operation->build());
    }

    /**
     * @dataProvider iterableInputDataProvider
     */
    public function test_it_builds_from_sources($input): void
    {
        $operation = BulkUpdateOperation::from($input, ':searchable_as:');

        self::assertEquals([
            ['index' => [ '_index' => ':searchable_as:', '_id' => ':scout_key:' ]],
            [ 'data' => true ]
        ], $operation->build());
    }

    public static function iterableInputDataProvider(): \Generator
    {
        yield 'collection' => [collect([new TestModelWithoutSettings()])];
        yield 'array' => [[new TestModelWithoutSettings()]];
        yield 'iterator' => [new \ArrayIterator([new TestModelWithoutSettings()])];
    }

    public function test_it_builds_with_preparation_of_model(): void
    {
        $operation = new BulkUpdateOperation(':searchable_as:');
        $operation->add(new TestModelWithPrepare());
        self::assertEquals([
            ['index' => [ '_index' => ':searchable_as:', '_id' => ':scout_key:' ]],
            [ 'data' => true, 'extra' => true ]
        ], $operation->build());
    }

    public function test_it_builds_with_preparation_of_index_action(): void
    {
        $operation = new BulkUpdateOperation(':searchable_as:');
        $operation->add(new TestModelWithIndexed());
        self::assertEquals([
            ['index' => [ '_index' => ':searchable_as:', '_id' => ':scout_key:', '_routing' => ':routing_key:' ]],
            [ 'data' => true ]
        ], $operation->build());
    }
}
