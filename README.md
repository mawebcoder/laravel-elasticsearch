
# laravel-elasticsearch (Elastiquent-PHP)

By using an Eloquent-like query builder, you can tame the beast without having to worry about Elasticsearch's monstrous syntax.

You can use this package if you are already familiar with Elasticsearch and want to save time by not having to memorize the higherarchical nested syntax.

As you would with migrations and models in Laravel, you can create and alter mappings and indices.

Those with a little more knowledge can also take advantage of so many advanced features of Elasticsearch in the package.

We will keep you updated on this amazing package in the future. :bomb: :sparkles: :computer:



@github/mawebcoder @github/KomeilShadan
# Publish config file and migration

``php artisan vendor:publish --tag=elastic``

Then  migrate your database :

``php artisan migrate``


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

By default this command rollbacks the migrations just one step. If you want to determine steps by yourself:

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

### Nested

```
$this->nested('nested_values');
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
$this->text('description');
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

or :

```
$eArticleModel=new EArticleModel();

$eArticleModel->create([
    'name'=>'mohammad',
    'age'=>29,
    //...
])

```

Just pay attention that if you pass any field that doesn't exist in your mappings you will encounter handled exception that prevents from storing invalid data into DB.

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

### Get mappings fields

```
$eArticleModel->getFields();
```

### Get specific field's value

```
$name=$eArticleModel->name;
```

# Future Releases

- Customizing  Normalizer and Tokenizer
- Raw Queries
- Aggregations
- Histograms
- Nested Search
- Edit existing mapping types
- Migration flag to create migration file automatically(-m)
