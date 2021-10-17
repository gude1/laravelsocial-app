<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProfilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('userid')->unique();
            $table->string('avatar')->nullable();
            $table->string('bio')->nullable();
            $table->boolean('is_campus')->default(true);
            $table->string('campus')->nullable();
            $table->string('profile_id')->unique();
            $table->string('profile_name')->nullable();
            $table->unsignedBigInteger('num_visits')->default(0);
            $table->unsignedBigInteger('num_followers')->default(0);
            $table->unsignedBigInteger('num_following')->default(0);
            $table->unsignedBigInteger('num_gists')->default(0);
            $table->unsignedBigInteger('num_posts')->default(0);
            $table->unsignedBigInteger('num_stories')->default(0);
            $table->unsignedBigInteger('created_at')->default(0);
            $table->unsignedBigInteger('updated_at')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('profiles');
    }
}
