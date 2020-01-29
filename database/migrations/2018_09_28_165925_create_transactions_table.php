<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionsTable extends Migration
{

    public function up()
    {
        Schema::create('Transactions', function(Blueprint $table) {
            $table->increments('id')->index();
            $table->uuid('transactions_id');
            $table->string('type');
            $table->string('part_1');
            $table->string('part_2');
            $table->string('state');
            $table->date('due_by');
            $table->decimal('total', 8, 0);
            $table->string('insured');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('Transactions');
    }
}
