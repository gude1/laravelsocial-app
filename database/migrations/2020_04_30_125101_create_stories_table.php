<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('storyid')->unique();
            $table->string('poster_id');
            $table->boolean('anonymous')->default(false);
            $table->mediumText('story_image')->nullable();
            $table->string('story_text')->nullable();
            $table->string('story_color')->default('green');
            $table->integer('num_story_likes')->default(0);
            $table->longText('story_likes_list')->nullable();
            $table->integer('num_views')->default(0);
            $table->longText('views_list')->nullable();
            $table->integer('num_story_comments')->default(0);
            $table->boolean('expired')->default(false);
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
        Schema::dropIfExists('stories');
    }
}
