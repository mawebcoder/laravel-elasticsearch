
# laravel-elasticsearch (Elastiquent-PHP)

By using an Eloquent-like query builder, you can tame the beast without having to worry about Elasticsearch's monstrous syntax.

You can use this package if you are already familiar with Elasticsearch and want to save time by not having to memorize the higherarchical nested syntax.

As you would with migrations and models in Laravel, you can create and alter mappings and indices.

Those with a little more knowledge can also take advantage of so many advanced features of Elasticsearch in the package.

We will keep you updated on this amazing package in the future. :bomb: :sparkles: :computer:



@github/mawebcoder @github/KomeilShadan


# Publish config file and migration

``php artisan vendor:publish --tag=elastic``

then migrate your database :

``php artisan migrate``


# ORM
this package works base on the ORM Database design to induce a more comfortable use experience in using elasticsearch.

# Models
To communicate with Elasticsearch, we need a model for each index.To create a model:

``php artisan elastic:make-model <model-name>``

by default your models base path is in ``app/Elasticsearch/Models`` directory, but you can  define your own base path  in config/elasticsearch.php file.

then you need to define your index name:.

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

to define your fields :


```
<?php

use Mawebcoder\Elasticsearch\Migration\BaseElasticMigration
use App\Elasticsearch\Models;


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



