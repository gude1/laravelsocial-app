<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRepliesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('replies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('originid');
            $table->string('replyid')->unique();
            $table->string('replyerid');
            $table->string('reply_text')->nullable();
            $table->string('reply_image')->nullable();
            $table->boolean('anonymous')->default(false);
            $table->integer('num_likes')->default(0);
            $table->integer('num_replies')->default(0);
            $table->timestamps(6);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('replies');
    }
}
