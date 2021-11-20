<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePrivateChatsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('private_chats', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('created_chatid');
            $table->string('private_chatid');
            $table->string('sender_id');
            $table->string('receiver_id');
            $table->mediumText('chat_msg')->nullable();
            $table->mediumText('chat_pics')->default(json_encode([]));
            $table->boolean('sender_deleted')->default(false);
            $table->boolean('receiver_deleted')->default(false);
            $table->string('read')->default('false');
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
        Schema::dropIfExists('private_chats');
    }
}
