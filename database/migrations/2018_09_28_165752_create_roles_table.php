<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRolesTable extends Migration
{

    public function up()
    {
        Schema::create('Roles', function(Blueprint $table) {
            $table->increments('id')->index();
            $table->uuid('role_id');
            $table->string('added_by');
            $table->string('role_name');
            $table->string('role_description');
            $table->string('role_availability');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::drop('Roles');
    }
}
