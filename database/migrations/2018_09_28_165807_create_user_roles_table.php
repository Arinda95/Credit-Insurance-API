<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserRolesTable extends Migration
{

    public function up()
    {
        Schema::create('UserRoles', function(Blueprint $table) {
            $table->increments('id')->index();
            $table->string('user_id');
            $table->string('role_id');
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::drop('UserRoles');
    }
}
