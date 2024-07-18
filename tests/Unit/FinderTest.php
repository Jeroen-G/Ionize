<?php

declare(strict_types=1);

namespace JeroenG\Explorer\Tests\Unit;

use Elastic\Elasticsearch\Client;
use InvalidArgumentException;
use JeroenG\Explorer\Application\AggregationResult;
use JeroenG\Explorer\Application\SearchCommand;
use JeroenG\Explorer\Domain\Aggregations\MaxAggregation;
use JeroenG\Explorer\Domain\Aggregations\NestedFilteredAggregation;
use JeroenG\Explorer\Domain\Aggregations\TermsAggregation;
use JeroenG\Explorer\Domain\Query\Query;
use JeroenG\Explorer\Domain\Syntax\Compound\BoolQuery;
use JeroenG\Explorer\Domain\Syntax\Matching;
use JeroenG\Explorer\Domain\Syntax\Sort;
use JeroenG\Explorer\Domain\Syntax\Term;
use JeroenG\Explorer\Infrastructure\Elastic\Finder;
use JeroenG\Explorer\Infrastructure\Scout\ScoutSearchCommandBuilder;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use JeroenG\Explorer\Domain\Aggregations\NestedAggregation;
use function var_dump;

class FinderTest extends MockeryTestCase
{
    private const TEST_INDEX = 'test_index';

    private const SEARCHABLE_FIELDS = [':field1:', ':field2:'];

    public function test_it_needs_an_index_to_even_try_to_find_your_stuff(): void
    {
        $client = Mockery::mock(Client::class);

        $builder = new SearchCommand();

        $subject = new Finder($client, $builder);

        $this->expectException(InvalidArgumentException::class);
        $subject->find();
    }

    public function test_it_finds_all_items_if_no_queries_are_provided(): void
    {
        $hit = $this->hit();

        $client = Mockery::mock(Client::class);
        $client->expects('search')
            ->with([
                'index' => self::TEST_INDEX,
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [],
                            'should' => [],
                            'filter' => [],
                        ],
                    ],
                ],
            ])
            ->andReturn([
                'hits' => [
                    'total' => ['value' => 1],
                    'hits' => [$hit],
                ],
            ]);

        $builder = new SearchCommand(self::TEST_INDEX, Query::with(new BoolQuery()));

        $subject = new Finder($client, $builder);
        $results = $subject->find();

        self::assertCount(1, $results);
        self::assertSame([$hit], $results->hits());
    }

    public function test_it_accepts_must_should_filter_and_where_queries(): void
    {
        $client = Mockery::mock(Client::class);
        $client->expects('search')
            ->with([
                'index' => self::TEST_INDEX,
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['match' => ['title' => ['query' => 'Lorem Ipsum', 'fuzziness' => 'auto']]],
                                ['multi_match' => ['query' => 'fuzzy search', 'fuzziness' => 'auto']],
                            ],
                            'should' => [
                                ['match' => ['text' => ['query' => 'consectetur adipiscing elit', 'fuzziness' => 'auto']]],
                            ],
                            'filter' => [
                                ['term' => ['published' => ['value' => true, 'boost' => 1.0]]],
                                ['term' => ['subtitle' => ['value' => 'Dolor sit amet', 'boost' => 1.0]]],
                                ['terms' => ['tags' => ['t1', 't2'], 'boost' => 1.0]],
                            ],
                        ],
                    ],
                ],
            ])
            ->andReturn([
                'hits' => [
                    'total' => ['value' => 2],
                    'hits' => [
                        $this->hit(),
                        $this->hit(),
                    ],
                ],
            ]);

        $builder = new ScoutSearchCommandBuilder();
        $builder->setIndex(self::TEST_INDEX);
        $builder->setMust([new Matching('title', 'Lorem Ipsum')]);
        $builder->setShould([new Matching('text', 'consectetur adipiscing elit')]);
        $builder->setFilter([new Term('published', true)]);
        $builder->setWheres(['subtitle' => 'Dolor sit amet']);
        $builder->setWhereIns(['tags' => ['t1', 't2']]);
        $builder->setQuery('fuzzy search');

        $subject = new Finder($client, $builder);
        $results = $subject->find();

        self::assertCount(2, $results);
    }

    public function test_it_accepts_a_query_for_paginated_search(): void
    {
        $client = Mockery::mock(Client::class);
        $client->expects('search')
            ->with([
                'index' => self::TEST_INDEX,
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [],
                            'should' => [],
                            'filter' => [],
                        ],
                    ],
                    'from' => 10,
                    'size' => 100,
                ],
            ])
            ->andReturn([
                'hits' => [
                    'total' => ['value' => 1],
                    'hits' => [$this->hit()],
                ],
            ]);

        $query = Query::with(new BoolQuery());
        $builder = new SearchCommand(self::TEST_INDEX);
        $builder->setQuery($query);
        $query->setOffset(10);
        $query->setLimit(100);

        $subject = new Finder($client, $builder);
        $results = $subject->find();

        self::assertCount(1, $results);
    }

    public function test_it_accepts_a_sortable_query(): void
    {
        $client = Mockery::mock(Client::class);
        $client->expects('search')
            ->with([
                'index' => self::TEST_INDEX,
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [],
                            'should' => [],
                            'filter' => [],
                        ],
                    ],
                    'sort' => [
                        ['id' => 'desc'],
                    ],
                ],
            ])
            ->andReturn([
                'hits' => [
                    'total' => ['value' => 1],
                    'hits' => [$this->hit()],
                ],
            ]);

        $query = Query::with(new BoolQuery());
        $builder = new SearchCommand(self::TEST_INDEX, $query);
        $query->setSort([new Sort('id', Sort::DESCENDING)]);

        $subject = new Finder($client, $builder);
        $results = $subject->find();

        self::assertCount(1, $results);
    }

    public function test_it_must_provide_offset_and_limit_for_pagination(): void
    {
        $client = Mockery::mock(Client::class);
        $client->expects('search')
            ->with([
                'index' => self::TEST_INDEX,
                'body' => [
                    'size' => 100,
                    'query' => [
                        'bool' => [
                            'must' => [],
                            'should' => [],
                            'filter' => [],
                        ],
                    ],
                ],
            ])
            ->andReturn([
                'hits' => [
                    'total' => ['value' => 1],
                    'hits' => [$this->hit()],
                ],
            ]);

        $query = Query::with(new BoolQuery());
        $builder = new SearchCommand(self::TEST_INDEX, $query);
        $builder->setIndex(self::TEST_INDEX);
        $query->setLimit(100);

        $subject = new Finder($client, $builder);
        $results = $subject->find();

        self::assertCount(1, $results);
    }

    public function test_it_builds_with_default_fields(): void
    {
        $client = Mockery::mock(Client::class);
        $client->expects('search')
            ->with([
                'index' => self::TEST_INDEX,
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['multi_match' => ['query' => 'fuzzy search', 'fields' => self::SEARCHABLE_FIELDS, 'fuzziness' => 'auto']],
                            ],
                            'should' => [],
                            'filter' => [],
                        ],
                    ],
                ],
            ])
            ->andReturn([
                'hits' => [
                    'total' => ['value' => 1],
                    'hits' => [$this->hit()],
                ],
            ]);

        $builder = new ScoutSearchCommandBuilder();
        $builder->setIndex(self::TEST_INDEX);
        $builder->setDefaultSearchFields(self::SEARCHABLE_FIELDS);
        $builder->setQuery('fuzzy search');

        $subject = new Finder($client, $builder);
        $results = $subject->find();

        self::assertCount(1, $results);
    }

    public function test_it_adds_fields_to_query(): void
    {
        $client = Mockery::mock(Client::class);
        $client->expects('search')
            ->with([
                'index' => self::TEST_INDEX,
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [],
                            'should' => [],
                            'filter' => [],
                        ],
                    ],
                    'fields' => ['*.length', 'specific.field'],
                ],
            ])
            ->andReturn([
                'hits' => [
                    'total' => ['value' => 1],
                    'hits' => [$this->hit()],
                ],
            ]);

        $query = Query::with(new BoolQuery());
        $builder = new SearchCommand(self::TEST_INDEX, $query);
        $builder->setIndex(self::TEST_INDEX);
        $query->setFields(['*.length', 'specific.field']);

        $subject = new Finder($client, $builder);
        $results = $subject->find();

        self::assertCount(1, $results);
    }

    public function test_it_adds_aggregates(): void
    {
        $client = Mockery::mock(Client::class);
        $client->expects('search')
            ->with([
                'index' => self::TEST_INDEX,
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [],
                            'should' => [],
                            'filter' => [],
                        ],
                    ],
                    'aggs' => [
                        'specificAggregation' => ['terms' => ['field' => 'specificField', 'size' => 10]],
                        'anotherAggregation' => ['terms' => ['field' => 'anotherField', 'size' => 10]],
                        'metricAggregation' => ['max' => ['field' => 'yetAnotherField']],
                    ],
                ],
            ])
            ->andReturn([
                'hits' => [
                    'total' => ['value' => 1],
                    'hits' => [$this->hit()],
                ],
                'aggregations' => [
                    'specificAggregation' => [
                        'buckets' => [
                            ['key' => 'myKey', 'doc_count' => 42],
                        ],
                    ],
                    'anotherAggregation' => [
                        'buckets' => [
                            ['key' => 'anotherKey', 'doc_count' => 6],
                        ],
                    ],
                    'metricAggregation' => [
                        'value' => 10,
                    ],
                ],
            ]);

        $query = Query::with(new BoolQuery());
        $query->addAggregation('specificAggregation', new TermsAggregation('specificField'));
        $query->addAggregation('anotherAggregation', new TermsAggregation('anotherField'));
        $query->addAggregation('metricAggregation', new MaxAggregation('yetAnotherField'));
        $builder = new SearchCommand(self::TEST_INDEX, $query);
        $builder->setIndex(self::TEST_INDEX);

        $subject = new Finder($client, $builder);
        $results = $subject->find();

        self::assertCount(3, $results->aggregations());

        $specificAggregation = $results->aggregations()[0];

        self::assertInstanceOf(AggregationResult::class, $specificAggregation);
        self::assertEquals('specificAggregation', $specificAggregation->name());
        self::assertCount(1, $specificAggregation->values());

        $specificAggregationValue = $specificAggregation->values()[0];

        self::assertEquals(42, $specificAggregationValue['doc_count']);
        self::assertEquals('myKey', $specificAggregationValue['key']);

        $metricAggregation = $results->aggregations()[2];

        self::assertArrayHasKey('value', $metricAggregation->values());
        self::assertEquals(10, $metricAggregation->values()['value']);
    }

    public function test_it_adds_nested_aggregations(): void
    {
        $client = Mockery::mock(Client::class);
        $client->expects('search')
            ->with([
                'index' => self::TEST_INDEX,
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [],
                            'should' => [],
                            'filter' => [],
                        ],
                    ],
                    'aggs' => [
                        'anotherAggregation' => ['terms' => ['field' => 'anotherField', 'size' => 10]],
                        'nestedFilteredAggregation' => [
                            'nested' => [
                                'path' => 'nestedFilteredAggregation',
                            ],
                            'aggs' => [
                                'filter_aggs' => [
                                    'filter' => [
                                        'bool' => [
                                            'should' => [
                                                'bool' => [
                                                    'must' => [
                                                        [
                                                            'terms' => [
                                                                'nestedFilteredAggregation.someFilter' => ['values'],
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    'aggs' => [
                                        'filter_aggs' => [
                                            'terms' => [
                                                'field' => 'nestedFilteredAggregation.someFieldNestedAggregation',
                                                'size' => 10,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'nestedAggregation' => [
                            'nested' => [
                                'path' => 'nestedAggregation',
                            ],
                            'aggs' => [
                                'someField' => [
                                    'terms' => [
                                        'field' => 'nestedAggregation.someField',
                                        'size' => 10,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ])
            ->andReturn([
                'hits' => [
                    'total' => ['value' => 1],
                    'hits' => [$this->hit()],
                ],
                'aggregations' => [
                    'nestedAggregation' => [
                        'doc_count' => 42,
                        'someField' => [
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' => [
                                ['key' => 'someKey', 'doc_count' => 6,],
                            ],
                        ],
                    ],

                    'nestedFilteredAggregation' => [
                        'doc_count' => 42,
                        'filter_aggs' => [
                            'doc_count' => 42,
                            'buckets' => [
                                ['key' => 'someFieldNestedAggregation_check', 'doc_count' => 6,],
                            ],
                            'someFieldFiltered' => [
                                'doc_count_error_upper_bound' => 0,
                                'sum_other_doc_count' => 0,
                                'buckets' => [
                                    ['key' => 'someFieldNestedAggregation', 'doc_count' => 6,],
                                ],
                            ],
                        ],
                    ],

                    'specificAggregation' => [
                        'buckets' => [
                            ['key' => 'myKey', 'doc_count' => 42],
                        ],
                    ],
                ],
            ]);

        $query = Query::with(new BoolQuery());
        $query->addAggregation('anotherAggregation', new TermsAggregation('anotherField'));
        $nestedAggregation = new NestedAggregation('nestedAggregation');
        $nestedAggregation->add('someField', new TermsAggregation('nestedAggregation.someField'));

        $filter = [
            'someFilter' => ['values'],
        ];
        $query->addAggregation(
            'nestedFilteredAggregation',
            new NestedFilteredAggregation('nestedFilteredAggregation', 'filter_aggs', 'someFieldNestedAggregation', $filter)
        );

        $query->addAggregation('nestedAggregation', $nestedAggregation);
        $builder = new SearchCommand(self::TEST_INDEX, $query);
        $builder->setIndex(self::TEST_INDEX);

        $subject = new Finder($client, $builder);
        $results = $subject->find();

        self::assertCount(4, $results->aggregations());

        $nestedAggregation = $results->aggregations()[0];

        self::assertInstanceOf(AggregationResult::class, $nestedAggregation);
        self::assertEquals('someField', $nestedAggregation->name());
        self::assertCount(1, $nestedAggregation->values());

        $nestedAggregationValue = $nestedAggregation->values()[0];

        self::assertEquals(6, $nestedAggregationValue['doc_count']);
        self::assertEquals('someKey', $nestedAggregationValue['key']);

        $nestedFilterAggregation = $results->aggregations()[2];

        self::assertInstanceOf(AggregationResult::class, $nestedFilterAggregation);
        self::assertEquals('someFieldFiltered', $nestedFilterAggregation->name());
        self::assertCount(1, $nestedFilterAggregation->values());

        $nestedFilterAggregationValue = $nestedFilterAggregation->values()[0];

        self::assertEquals(6, $nestedFilterAggregationValue['doc_count']);
        self::assertEquals('someFieldNestedAggregation', $nestedFilterAggregationValue['key']);
    }

    public function test_with_single_aggregation(): void
    {
        $client = Mockery::mock(Client::class);
        $client->expects('search')
            ->with([
                'index' => self::TEST_INDEX,
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [],
                            'should' => [],
                            'filter' => [],
                        ],
                    ],
                    'aggs' => [
                        'anotherAggregation' => ['terms' => ['field' => 'anotherField', 'size' => 10]],
                    ],
                ],
            ])
            ->andReturn([
                'hits' => [
                    'total' => ['value' => 1],
                    'hits' => [$this->hit()],
                ],
                'aggregations' => [
                    'nestedAggregation' => [
                        'doc_count' => 42,
                        'someField' => [
                            'doc_count_error_upper_bound' => 0,
                            'sum_other_doc_count' => 0,
                            'buckets' => [
                                ['key' => 'someKey', 'doc_count' => 6,],
                            ],
                        ],
                    ],
                ],
            ]);

        $query = Query::with(new BoolQuery());
        $query->addAggregation('anotherAggregation', new TermsAggregation('anotherField'));

        $builder = new SearchCommand(self::TEST_INDEX, $query);
        $builder->setIndex(self::TEST_INDEX);

        $subject = new Finder($client, $builder);
        $results = $subject->find();

        self::assertCount(1, $results->aggregations());
    }

    public function test_it_with_no_aggregations(): void
    {
        $client = Mockery::mock(Client::class);
        $client->expects('search')
            ->with([
                'index' => self::TEST_INDEX,
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [],
                            'should' => [],
                            'filter' => [],
                        ],
                    ],
                ],
            ])
            ->andReturn([
                'hits' => [
                    'total' => ['value' => 1],
                    'hits' => [$this->hit()],
                ],
            ]);

        $query = Query::with(new BoolQuery());
        $builder = new SearchCommand(self::TEST_INDEX, $query);
        $builder->setIndex(self::TEST_INDEX);

        $subject = new Finder($client, $builder);
        $results = $subject->find();

        self::assertCount(0, $results->aggregations());
    }

    private function hit(int $id = 1, float $score = 1.0): array
    {
        return [
            '_index' => self::TEST_INDEX,
            '_type' => 'default',
            '_id' => (string) $id,
            '_score' => $score,
            '_source' => [],
        ];
    }
}
