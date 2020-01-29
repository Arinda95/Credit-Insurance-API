<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTokensTable extends Migration
{

    public function up()
    {
        Schema::create('Tokens', function(Blueprint $table) {
            $table->increments('id')->index();
            $table->string('user_id');
            $table->string('token_hash');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('Tokens');
    }
}
