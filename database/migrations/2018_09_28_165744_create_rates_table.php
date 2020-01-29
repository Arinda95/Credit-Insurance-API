<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRatesTable extends Migration
{

    public function up()
    {
        Schema::create('Rates', function(Blueprint $table) {
            $table->increments('id')->index();
            $table->string('rate_name');
            $table->decimal('percentage_rate', 3, 0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('Rates');
    }
}
