<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTestEmailsTable extends Migration
{

    public function up()
    {
        Schema::create('TestEmails', function(Blueprint $table) {
            $table->increments('id')->index();
            $table->string('from_email');
            $table->string('to_email');
            $table->string('title');
            $table->string('message');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('TestEmails');
    }
}
