<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAdminsTable extends Migration
{

    public function up()
    {
        Schema::create('Admins', function(Blueprint $table) {
            $table->increments('id')->index();
            $table->uuid('admin_id');
            $table->string('fname');
            $table->string('lname');
            $table->date('date_of_birth');
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
        Schema::drop('Admins');
    }
}
