
# laravel-elasticsearch (Elastiquent-PHP)

By using an Eloquent-like query builder, you can tame the beast without having to worry about Elasticsearch's monstrous syntax.

You can use this package without needing to master Elasticsearch deeply and save your time by not having to memorize the higherarchical nested syntax.

## Dependencies

| Dependency    | version |
|---------------|---------|
| Elasticsearch | ^7.17.9 |
| Laravel       | ^9.0     |
| php           | ^8.1    |


# Config file and Elasticsearch migration log

In order to be able to save logs related to migrations and customize your configurations, you need to run command below:

``php artisan vendor:publish --tag=elastic``

Then  migrate your database :

``php artisan migrate``

# Config

After publishing the config file, you will see the following content
``` 

return [
    'index_prefix' => env('APP_NAME', 'elasticsearch'),
    'host' => 'http://localhost',
    'port' => 9200,
    'reindex_migration_driver' => "sync", //sync or queue,
    "reindex_migration_queue_name" => 'default',
    'base_migrations_path' => app_path('Elasticsearch/Migrations'),
    'base_models_path' => app_path('Elasticsearch/Models'),
    "username" => env('ELASTICSEARCH_USERNAME', null),
    'password' => env('ELASTICSEARCH_PASSWORD', null)
];
```

# ORM
This package has added ``ORM`` functionality to make it easier to work with documentation, just like what we see in Laravel.We will get to know more as we go forward.Let's dive into it

# Models
to be able to have a more effective relationship with our documents, we need to have a model for each index. Models similar to what we see in Laravel greatly simplify the work of communicating with the database.

In order to create a Model:

`php artisan elastic:make-model <model-name>`

By default, your models base path is in ``app/Elasticsearch/Models`` directory, But you can  define your own base path  in ``config/elasticsearch.php`` file.

All your models must inherit from the `BaseElasticsearchModel` class. This class is an abstract class that enforce you to implement the `getIndex` method that returns the index name of  model.

```
public function getIndex():string 

{
    return 'articles';
}

```
We use the return value of this method to create the index you want in migrations.

If you want to get your index name with the prefix that you defined in config file:

```
$model->getIndexWithPrefix();
```
# Migrations

As you may know, Elasticsearch uses mappings for the structure of its documents, which may seem a little difficult to create in raw form. In order to simplify this process, we use migrations to make this process easier.
After defining the model, you have to create a migration to register your desired fields.All your migrations must inherit from the `BaseElasticMigration`   abstract class.

To Create a new Migration:

``php artisan elastic:make-migration <migration-name>``

By default, your migrations base path is in ``app/Elasticsearch/Migrations`` directory, but you can define your own base path in ``config/elasticsearch.php`` file.


```
<?php

//app/Elasticsearch/Migrations

use Mawebcoder\Elasticsearch\Migration\BaseElasticMigration
use App\Elasticsearch\Models\EArticleModel;


return new class extends BaseElasticMigration {

public function getModel():string 
{

    return EArticleModel::class;
}


 public function schema(BaseElasticMigration $mapper): void
    {
        $mapper->integer('id');
        $mapper->string('name');
        $mapper->boolean('is_active');
        $mapper->text('details');
        $mapper->integer('age');
        $mapper->object('user',[
            'name'=>self::TYPE_STRING,
            'id'=>self::TYPE_BIGINT
        ]);
    }

};

```

Unfortunately, the package cannot automatically find the path of your migrations. To introduce the path of migrations,put the sample code below in one of your providers:

```
use Mawebcoder\Elasticsearch\Facade\Elasticsearch;

 public function register(): void
    {
        Elasticsearch::loadMigrationsFrom(__DIR__ . '/../Elastic/Migrations');
        Elasticsearch::loadMigrationsFrom(__DIR__ . '/../App/Migrations');
    }

```


To see migrations states :

``php artisan elastic:migrate-status``

To migrate migrations and create your indices mappings :

``php artisan elastic:migrate``

To reset all migrations(this command just runs `down` method in all migrations) :

``php artisan elstic:migrate --reset``

To drop all indices and register them again:

``php artisan elastic:migrate --fresh``

To rollback migration:

``php artisan elastic:migrate-rollback``

By default, this command rollbacks the migrations just one step.if you want to determine steps by yourself:

``php artisan elastic:migrate-rollback --step=<number>``


## Field Types


### Integer

```
$this->integer('age');
```

### String(keyword)

```
$this->string('name');
```

### Object

```
$this->object('object_field',[
    'name'=>self::TYPE_STRING,
    'age'=>self::TYPE_INTEGER
]);
```

### Boolean
```
$this->boolean('is_active');
```

### SmallInteger(short)

```
$this->smallInteger('age');
```

### BigInteger(long)

```
$this->bigInteger('income');
```

### Double
```
$this->double('price');
```

### Float

```
$this->float('income');
```

### TinyInt(byte)

```
$this->tinyInt('value');
```

### Text

```
$this->text(field:'description',fieldData:true);
```


### DateTime(date)

```
$this->datetime('created_at');
```


# Edit Indices Mappings

Sometimes you need modify your mappings. To do this
you have to add a new migration:

``php artisan elastic:make-migration <alter migration name>``

```
<?php

use Mawebcoder\Elasticsearch\Migration\BaseElasticMigration;
use Mawebcoder\Elasticsearch\Models\EArticleModel;
use Mawebcoder\Elasticsearch\Migration\AlterElasticIndexMigrationInterface;

return new class extends BaseElasticMigration implements AlterElasticIndexMigrationInterface {
    public function getModel(): string
    {
        return EArticleModel::class;
    }

    public function schema(BaseElasticMigration $mapper): void
    {
        $mapper->dropField('name');
        $mapper->boolean('new_field');//add this field to 
    }

    public function alterDown(BaseElasticMigration $mapper): void
    {
        $mapper->string('name');
        $mapper->dropField('new_field');
    }
};
```
As you can see, we implemented ``AlterElasticIndexMigrationInterface`` interface in our migration. Then in alterDown method we wrote our rollback scenario.
Finally, migrate your migration:

``php artisan elastic:migrate``

### Dynamic Mapping
By default, Elasticsearch detects the type of fields that you have not introduced in mapping and defines its type automatically. The package has disabled it by default. To activate it, do the following in your migration:

```
protected bool $isDynamicMapping = true;
```

# Query Builder

Just like Laravel, which enabled you to create complex and crude queries by using a series of pre-prepared methods, this package also uses this feature to give you a similar experience.
### Store a recorde

``` php
$eArticleModel=new EArticleModel();

$eArticleModel->name='mohammad';

$eArticleModel->id=2;

$eArticleModel->is_active=true;

$eArticleModel->user=[
    'name'=>'komeil',
    'id'=>3
];

$eArticleModel->text='your text';

$eArticleModel->age=23;

$eArticleModel->save();

```

- Note: If you don't pass any field that exists in your mappings,we set that as null by default

### Store Several Records (Bulk Insert)

``` php
$users = [
    [
        id => 1,
        name => 'Mohsen',
        is_active => true,
        text => 'your text',
        age => 25
    ],
    [
        id => 2,
        name => 'Ali',
        is_active => true,
        text => 'your text',
        age => 20
    ]
];

EUserModel::newQuery()->saveMany($users);
```

### Find record

```
$eArticleModel=new EArticleModel();

$result=$eArticleModel->find(2);

```

### Remove record

```
$eArticleModel=new EArticleModel();

$result=$eArticleModel->find(2);

$result?->delete();
```

### Conditions


####  Equal

```
$eArticleModel
->where('id',2)
->orWhere('id',2)
->get();
```

#### Not Equal
```
$eArticleModel
->where('id','<>',2)
->orWhere('id','<>',10)
->get();
```

- Note:Do not use ``=``,``<>``  operators on ``text`` fields because we used term in these operators.in ``text`` field you need to use ``like`` or ``not like`` operator instead

#### Greater Than

```
$eArticleModel->where('id','>',10)->orWhere('id','>=',20)->get();
```

#### Lower Than

```
$eArticleModel->where('id','<',2)->orWhere('id',null)->first();
```

#### Like

```
$eArticleModel->where('name','like','foo')->orWhere('name','not like','foo2')->get();
```

#### whereTerm
Sometimes you want to search for a specific phrase in a text. In this case, you can do the following :

```
$eArticleModel->whereTerm('name','foo')->orWhereTerm('name','<>','foo2')->get();
```

#### whereIn

```
$eArticleModel->whereIn('id',[1,2])->orWhereIn('age',[23,26])->first();
```

#### whereNotIn

```
$eArticleModel->whereNotIn('id',[1,2])->orWhereNotIn('id',[26,23])->get();
```

#### whereBetween

```
$eArticleModel->whereBetween('id,[1,2])->orWhereBetween('age',[26,27])->first();
```


#### whereNotBetween

```
$eArticleModel->whereNotBetween('id',[1,2])->orWhereNotBetween('id,'26,27])->get();
```

### whereNull

```
$eArticleModel->whereNull('id')->orWhereNull('name')->first();
```

### whereNotNull

```
$eArticleModel->whereNotNull('id')->orWhereNotNull()->first();
```

#### Chaining

```
$eArticleModel=new EArticleModel();

$eArticleModel
->where('name','<>','mohammad')
->orWhere('name,'komeil')
->whereNotIn('id,[1,2,3])
->where('name','like','value')
->whereBetween('id',[10,13])
->whereNotBetween('id',[1,2])
->whereTerm('name','foo')
->get();

```

#### Fuzzy Search

Note: fuzzy search just works on the text fields

```
$eArticleModel=new EArticleModel();

$eArticleModel
->whereFuzzy('name','mawebcoder',fuzziness:3)
->get();

```

You can change the fuzziness value as you want

#### Get pure Query

```
$eArticleModel->where('id','like','foo')->dd();
```

### Update record

```

$eArticleModel=new EArticleModel();

$result=$eArticleModel->find(2);

$result?->update([
    'name'=>'elastic',
    //...
]);

```


### Bulk update


```
$eArticleModel=new EArticleModel();

$eArticleModel
->where('name','<>','mohammad')
->update([
    'name'=>'elastic',
    //...
]);

```

### Bulk delete


```
$eArticleModel=new EArticleModel();

$eArticleModel
->where('name','<>','mohammad')
->delete();

```

### Take(limit)

```
$eArticleModel=new EArticleModel();

$eArticleModel
->where('name','<>','mohammad')
->take(10)
->get();

```

### Offset

```
$eArticleModel=new EArticleModel();

$eArticleModel
->where('name','<>','mohammad')
->take(10)
->offset(5)
->get();

```

### select


```
$eArticleModel=new EArticleModel();

$eArticleModel
->where('name','<>','mohammad')
->select('name,'age','id')
->get();

```

### OrderBy


```
$eArticleModel=new EArticleModel();

$eArticleModel
->where('name','<>','mohammad')
->select('name,'age')
->orderBy('age','desc')
->get();

```

- Note:Do not use orderBy on ``text`` fields because text is not optimized for sorting operation in Elasticsearch.But if you want to force to sort the texts set the fielddata=true:

``` 
$this->text(field:"description",fielddata:true) 
```
By Adding above line you can use texts as sortable and in aggregations,but  fielddata uses significant memory while indexing


### Get specific field's value

```
$name=$eArticleModel->name;
```


### Nested Search

First of all we need to define ``object`` type in our migration:

``` 

"category":{
            "name":"elastic",
            "id":2
          }

```

``` 
$mapper->object('categories',[
            'name'=>self::TYPE_STRING,
            'id'=>self::TYPE_BIGINT
        ]);
```

```
$eArticleModel
->where('categories.name')->first();
```

If you have multi dimension objects like below:

``` 

"categories":[
           {
            "name":"elastic",
            "id":2
           },
           {
            "name":"mohammad",
            "id":3
           }


            ]

```
Define your mappings Like below:

``` 
$mapper->object('categories',[
            'name'=>self::TYPE_STRING,
            'id'=>self::TYPE_BIGINT
        ]);
```

```
$eArticleModel
->where('categories.name')->first();
```

### Destroy by id

``` 
$eArticleModel->destroy([1,2,3]);
```

### Nested Queries

In order to create complex and nested queries, you can use the nesting function of the builder. There is no limit to the nesting of your queries:

```
$model=Model::newQuery()
->where('name','john')
->where(function($model){
        return $model->where('age',22)
        ->orWhere(function($model){
        return $model->whereBetween('range',[1,10]);
        })
})->orWhere(function($model){
    return $model->where('color','red')
    ->orWhereIn('cars',['bmw','buggati'])
})->get()

```
Just pay attention that you need to return the queries inside closure otherwise
it will be ignored


### chunk

for better managing your memory usage you can use the chunk method :

```
$model=Model::newQuery()
->where('name','mohammad')
->chunk(100,function(Collection $collection){
    //code here
})
```

### Aggregations

By default, all related data  also will be return, If you want just aggregations be in your result use ``take(0)`` to prevent oveloading data in you request

#### Count

``` 
$eArticleModel
->where('categories.name')
->orWhere('companies.title','like','foo')
->count();
```
#### bucket

``` 
$eArticleModel
->bucket('languages','languages-count')
->get();

$aggregations=$eArticleModel['aggregations'];

```

By default, bucket method returns maximum ``2147483647`` number of the records,if You want to change it:

``` 
$eArticleModel
->bucket('languages','languages-count',300) //returns 300 records maximum
->get();
```


### Min

``` 
$model->min('year')->get()
```

### Max

``` 
$model->max('year')->get()
```

### Avg

``` 
$model->avg('year')->get()
```

### Sum

``` 
$model->sum('year')->get()
```

### Unique
Sometimes you need to retrieve only unique records based on an criterion:
``` 
$model->where('category','elastic')->unique('name');
```

### groupBy
In order to group data based on a criterion

``` 
$model->where('category','elastic')->groupby(field:'age',sortField:'category',direction:'desc');
```

### Pagination
```
$eArticleModel
->where('categories.name')
->orWhere('companies.title','like','foo')
->paginate()
```

By default paginate methods paginates pages per 15 items,but you can change it:

``` 
$eArticleModel
->where('categories.name')
->paginate(20)
```
The result will be something like this:
``` 
[
    "data"=>[...],
    'total_records' => 150,
    'last_page' => 12,
    'current_page' => 9,
    'next_link' => localhost:9200?page=10,
    'prev_link' => localhost:9200?page=8,
]
```
# Interact With Documentations

### Drop indices by name

```

Elasticsearch::dropIndexByName($model->getIndexWithPrefix())
```

### Check index Exists

```

Elasticsearch::hasIndex($model->getIndexWithPrefix())
```

### Get all indices

```

Elasticsearch::getAllIndexes()
```

### Drop index by model

```

Elasticsearch::setModel(EArticle::class)->dropModelIndex()
```

### Get all model fields

```

Elasticsearch::setModel(EArticle::class)->getFields()
```

### Get model mappings

```

Elasticsearch::setModel(EArticle::class)->getMappings()
```


# Coming soon
- Histogram
- Define normalizer and tokenizer
- Raw Queries
