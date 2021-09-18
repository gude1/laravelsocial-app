<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePostsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('postid')->unique();
            $table->string('poster_id');
            $table->boolean('anonymous')->default(false);
            $table->mediumText('post_image')->nullable();
            $table->string('post_text')->nullable();
            $table->integer('num_post_likes')->default(0);
            $table->boolean('archived')->default(false);
            $table->boolean('deleted')->default(false);
            $table->integer('num_post_shares')->default(0);
            $table->longText('post_shares_list')->nullable();
            $table->integer('num_post_comments')->default(0);
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
        Schema::dropIfExists('posts');
    }
}
