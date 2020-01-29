<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateNotificationsTable extends Migration
{

    public function up()
    {
        Schema::create('Notifications', function(Blueprint $table) {
            $table->increments('id')->index();
            $table->uuid('notification_id');
            $table->string('recipient_id');
            $table->string('type');
            $table->string('title');
            $table->string('body');
            $table->string('post_script');
            $table->string('read_state');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('Notifications');
    }
}
