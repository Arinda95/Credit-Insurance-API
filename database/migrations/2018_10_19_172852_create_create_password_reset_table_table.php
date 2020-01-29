<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCreatePasswordResetTableTable extends Migration
{

    public function up()
    {
        Schema::create('credential_reset', function(Blueprint $table) {
            $table->increments('id')->index();
            $table->uuid('user_id');
            $table->string('user_type');
            $table->string('credential_type');
            $table->string('email_key_hash');
            $table->string('sms_key_hash');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('credential_reset');
    }
}
