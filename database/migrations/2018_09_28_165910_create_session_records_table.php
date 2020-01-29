<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSessionRecordsTable extends Migration
{

    public function up()
    {
        Schema::create('SessionRecords', function(Blueprint $table) {
            $table->increments('id')->index();
            $table->uuid('session_id');
            $table->string('user_id');
            $table->ipAddress('user_ip');
            $table->string('client');
            $table->dateTime('started_at');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('SessionRecords');
    }
}
