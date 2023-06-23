
# laravel-elasticsearch (Elastiquent-PHP)

By using an Eloquent-like query builder, you can tame the beast without having to worry about Elasticsearch's monstrous syntax.

You can use this package if you are already familiar with Elasticsearch and want to save time by not having to memorize the higherarchical nested syntax.

As you would with migrations and models in Laravel, you can create and alter mappings and indices.

Those with a little more knowledge can also take advantage of so many advanced features of Elasticsearch in the package.

We will keep you updated on this amazing package in the future. :bomb: :sparkles: :computer:



## Dependencies

| Dependency    | version |
|---------------|---------|
| Elasticsearch | ^7.17.9 |
| Laravel       | ^10     |
| php           | ^8.1    |



@github/mawebcoder @github/KomeilShadan
# Publish config file and migration

``php artisan vendor:publish --tag=elastic``

Then  migrate your database :

``php artisan migrate``

# Config 

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
This package works based on the ORM Database design to induce a more comfortable use experience in using elasticsearch.

# Models
To communicate with Elasticsearch, we need a model for each index.To create a model:

``php artisan elastic:make-model <model-name>``

By default, your models base path is in ``app/Elasticsearch/Models`` directory, But you can  define your own base path  in ``config/elasticsearch.php`` file.

Then you need to return your index name in ``getIndex`` method :

```
public function getIndex():string 

{
    return 'articles';
}

```

# Migrations

After defining the model, you have to create a migration to register your desired fields:

``php artisan elastic:make-migration <migration-name>``

By default your migrations base path is in ``app/Elasticsearch/Migrations`` directory, but you can define your own base path in ``config/elasticsearch.php`` file.


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

To load  migrations in  AppServiceProvider:

```
use Mawebcoder\Elasticsearch\Facade\Elasticsearch;

 public function register(): void
    {
        Elasticsearch::loadMigrationsFrom(__DIR__ . '/../Elastic/Migrations');
    }

```


To see migrations states :

``php artisan elastic:migrate-status``

To migrate migrations and create your indices mappings :

``php artisan elastic:migrate``

To reset all migrations :

``php artisan elstic:migrate --reset``

To fresh all migrations :

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

Sometimes you need to add or drop fields from your indices mapping. To do this
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

# Query Builder

### Store a recorde

```
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

- Note: If you pass any field that doesn't exist in your mappings you will encounter handled exception that prevents from storing invalid data into DB.
- Note: If you don't pass any field that exists in mapping,we set that as null by default
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
$eArticleModel->where('id','<',2)->orWhere('id','<=',1)->first();
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

### Callback Conditions

```
$eArticleModel=new EArticleModel();

$eArticleModel
->where(function(EArticleModel $eArticleModel){
$eArticleModel->where('name,'ali')
->orWhereIn('id',[1,2,3]);
})

->get();

```


Note: By default Elasticsearch retrieve 10 records,if you want to set more ,just use ``take($records)`` method,
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
->select('name,'age')
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

### Conditional Queries


``` 
$model=new ElasticModel();

$model->when($condition,function($model){
$model->where('age','>=',12);
});
```

### Get mappings fields

```
$eArticleModel->getFields();
```

### Get mappings

``` 
$eArticleModel->getMappings();
```

### Get specific field's value

```
$name=$eArticleModel->name;
```

To  export from your raw queries
```
$eArticleModel
->where('name','<>','mohammad')
->select('name,'age')
->orderBy('age','desc')
->dd();
```

### Nested Query

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

### Aggregations

By default, all data will be return, If you want just aggregations be in your result use ``take(0)`` to prevent oveloading data in you request

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

By default, bucket method returns ``2147483647`` number of the records,if You want to change it:

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


# Coming soon
- Histogram
- Define normalizer and tokenizer 
- Raw Queries
