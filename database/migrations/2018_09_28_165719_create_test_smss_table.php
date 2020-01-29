<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTestSmssTable extends Migration
{

    public function up()
    {
        Schema::create('TestSmss', function(Blueprint $table) {
            $table->increments('id')->index();
            $table->string('from_number');
            $table->string('to_number');
            $table->string('title');
            $table->string('message');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('TestSmss');
    }
}
