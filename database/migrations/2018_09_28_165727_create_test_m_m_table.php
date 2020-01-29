<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTestMMTable extends Migration
{

    public function up()
    {
        Schema::create('TestMM', function(Blueprint $table) {
            $table->increments('id')->index();
            $table->uuid('phonenumber_id');
            $table->string('phonenumber');
            $table->decimal('amount', 12, 0);
            $table->string('pin');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('TestMM');
    }
}
