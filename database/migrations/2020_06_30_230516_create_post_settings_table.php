<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePostSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('post_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('timeline_post_range')->default('all');
            $table->longText('blacklisted_posts')->default(json_encode([]));
            $table->string('profile_id')->unique();
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
        Schema::dropIfExists('post_settings');
    }
}
