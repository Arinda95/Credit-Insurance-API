<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWalletsTable extends Migration
{

    public function up()
    {
        Schema::create('Wallets', function(Blueprint $table) {
            $table->increments('id')->index();
            $table->uuid('wallet_id');
            $table->string('type');
            $table->string('user_id');
            $table->decimal('balance', 12, 0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::drop('Wallets');
    }
}
