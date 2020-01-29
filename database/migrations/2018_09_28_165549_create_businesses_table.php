<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBusinessesTable extends Migration
{

    public function up()
    {
        Schema::create('businesses', function(Blueprint $table) {
            $table->increments('id')->index();
            $table->uuid('business_id');
            $table->string('name');
            $table->string('branch');
            $table->string('location');
            $table->string('type');
            $table->integer('pin');
            $table->string('password');
            $table->string('phonenumber');
            $table->string('email');
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::drop('businesses');
    }
}
