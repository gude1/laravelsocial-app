<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCreateChatsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('create_chats', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('chatid')->unique();
            $table->string('chat_type')->default('normal');
            $table->string('profile_id1');
            $table->string('profile_id2');
            $table->integer('profile_id1_lastvist')->default(0);
            $table->integer('profile_id2_lastvist')->default(0);
            $table->longText('profile_id1_last_fetch_arr')->default(json_encode([]));
            $table->longText('profile_id2_last_fetch_arr')->default(json_encode([]));
            $table->integer('created_at')->default(0);
            $table->integer('updated_at')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('create_chats');
    }
}
