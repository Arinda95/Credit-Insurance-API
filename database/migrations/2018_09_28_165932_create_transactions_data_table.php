<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionsDataTable extends Migration
{

    public function up()
    {
        Schema::create('TransactionsData', function(Blueprint $table) {
            $table->increments('id')->index();
            $table->uuid('data_id');
            $table->string('item');
            $table->string('qty');
            $table->decimal('price', 8, 0);
            $table->integer('amount');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('TransactionsData');
    }
}
