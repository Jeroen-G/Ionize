# Preparing data
[Text analysis](text-analysis.md) is perfect when you want to alter how Elasticsearch deals with a query, or with the data when it is being indexed.
However, sometimes you do not necessarily want to meddle with analyzer, but rather just make a quick change to the data _before_ it is indexed, perhaps conditionally.

For this, Explorer gives you the ability to 'prepare' your searchable data.
To use this you will need to implement the `JeroenG\Explorer\Application\BePrepared` interface on your model and add the `prepare($data)` function.
The data that is passed to the prepare function is what Laravel Scout [generates](https://laravel.com/docs/scout#configuring-searchable-data) in the `toSearchableArray()` method.

The prepared data might be very simple:

```php
public function prepare(array $searchable): array
{
    $searchable['name'] = ucfirst($searchable['name']);

    return $searchable;
}
```

Or you could use something like Laravel's pipelines to do much more complex stuff:

```php
public function prepare(array $searchable): array
{
    $searchable['content'] = (new Illuminate\Pipeline\Pipeline())
                ->send($searchable['content'])
                ->through([
                    App\Formatters\ConvertMarkdown::class,              
                    App\Formatters\StripTags::class,
                    App\Formatters\EncodeEmoji::class,
                ])
                ->thenReturn();

    return $searchable;
}
```

# Preparing Index Action
Sometimes may you want to [route your documents](https://www.elastic.co/guide/en/elasticsearch/reference/current/mapping-routing-field.html) to a specific shard.

To achieve this, Explorer provides the `prepareIndexAction` method, which allows you to [add metadata to the Bulk action](https://www.elastic.co/guide/en/elasticsearch/reference/8.6/docs-bulk.html#bulk-routing). To utilize this feature, you need to implement the `JeroenG\Explorer\Application\BeIndexed` interface in your model and add the `prepareIndexAction(array $action)` function. The data passed to the prepare function is the index action that will be used when the document is sent to Elasticsearch.

If you have a multi-tenant system and know that the queries will always include a filter condition for a given tenant, you might want to instruct Elasticsearch to group the documents for the same tenant on the same shard. Here's an example:

```php
public function prepareIndexAction(array $action): array
{
    $action['index']['_routing'] = $this->tenant_id;

    return $action;
}
```
