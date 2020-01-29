<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSessionsTable extends Migration
{

    public function up()
    {
        Schema::create('Sessions', function(Blueprint $table) {
            $table->increments('id')->index();
            $table->uuid('session_id');
            $table->string('user_id');
            $table->string('token');
            $table->string('client');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('Sessions');
    }
}
