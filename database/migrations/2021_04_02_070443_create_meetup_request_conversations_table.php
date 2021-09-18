<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMeetupRequestConversationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('meetup_request_conversations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('meet_request_id');
            $table->string('conversation_id');
            $table->string('sender_id');
            $table->string('receiver_id');
            $table->mediumText('chat_msg')->nullable();
            $table->string('chat_pic')->nullable();
            $table->enum('status', ['sent', 'delievered', 'read'])->default('sent');
            $table->integer('created_at');
            $table->integer('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('meetup_request_conversations');
    }
}
