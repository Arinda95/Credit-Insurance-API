<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCustomersTable extends Migration
{

    public function up()
    {
        Schema::create('Customers', function(Blueprint $table) {
            $table->increments('id')->index();
            $table->string('customer_id');
            $table->string('fname');
            $table->string('lname');
            $table->string('date_of_birth');
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
        Schema::drop('Customers');
    }
}
