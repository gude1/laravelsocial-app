<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMeetupSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('meetup_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('owner_id')->unique();
            $table->string('meetup_name')->nullable();
            $table->string('meetup_avatar')->nullable();
            $table->longText('black_listed_arr')->default(json_encode([]));
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('meetup_settings');
    }
}
