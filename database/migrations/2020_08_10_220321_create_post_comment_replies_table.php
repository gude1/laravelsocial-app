<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePostCommentRepliesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('post_comment_replies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('originid');
            $table->string('replyid')->unique();
            $table->string('replyer_id');
            $table->string('reply_image')->nullable();
            $table->string('reply_text')->default('');
            $table->boolean('anonymous')->default(false);
            $table->boolean('hidden')->default(false);
            $table->boolean('deleted')->default(false);
            $table->integer('num_replies')->default(0);
            $table->integer('num_likes')->default(0);
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
        Schema::dropIfExists('post_comment_replies');
    }
}
