
# laravel-elasticsearch (Elastiquent-PHP)

By using an Eloquent-like query builder, you can tame the beast without having to worry about Elasticsearch's monstrous syntax.

You can use this package if you are already familiar with Elasticsearch and want to save time by not having to memorize the higherarchical nested syntax.

As you would with migrations and models in Laravel, you can create and alter mappings and indices.

Those with a little more knowledge can also take advantage of so many advanced features of Elasticsearch in the package.

We will keep you updated on this amazing package in the future. :bomb: :sparkles: :computer:



@github/mawebcoder @github/KomeilShadan
# publish config file and migration

``php artisan vendor:publish --tag=elastic``

then  migrate your database :

``php artisan migrate``


# ORM
this package works base on the ORM Database design to induce a more comfortable use experience in using elasticsearch.

# Models
To communicate with Elasticsearch, we need a model for each index.To create a model:

``php artisan elastic:make-model <model-name>``

by default your models base path is in ``app/Elasticsearch/Models`` directory, but you can  define your own base path  in config/elasticsearch.php file.

then you need to return your index name in ``getIndex`` method :

```
public function getIndex():string 

{
    return 'articles';
}

```

# Migrations

after defining the model,you have to create a migration to register your desired fields:

``php artisan elastic:make-migration <migration-name>``

by default your migrations base path is in ``app/Elasticsearch/Migrations`` directory, but you can  define your own base path  in config/elasticsearch.php file.


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

To migrate  migrations and create your indices mappings :

``php artisan elastic:migrate``

To reset the all migrations :

``php artisan elstic:migrate --reset``

To fresh all Migrations :

``php artisan elastic:migrate --fresh``

To rollback Migration:

``php artisan elastic:migrate-rollback``

by default this command rollbacks the migrations just one step.if you want to determine steps by yourself:

``php artisan elastic:migrate-rollback --step=<number>``



# Edit Indices Mappings

Sometime you need to add or drop fields from your indice mapping.for doing this 
you have to add new migration:

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
as you can see we implements ``AlterElasticIndexMigrationInterface`` interface in our migration.then in alterDown method we wrote our rollback senario.
finally migrate your migration:

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

Just pay attention that if you pass any field that doesn't exist in your mappings you will encounter handled error that prevents from storing invalid data into DB.

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
->first();
```
There are more conditions that base on your requirements you can use them.


### Get Results

In example below you will get an collection of the EArticleModel instances and you access the laravel Collections Features:

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

### Buld delete


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


# Future Releases

- customizing  Normalizer and Tokenizer
- Aggregations
- Histograms
- Search in multiple dimension fields
- Edit existing mapping types 









