<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('type');
            $table->boolean('is_mention')->default(false);
            $table->string('mentioned_name')->nullable();
            $table->string('initiator_id');
            $table->string('receipient_id');
            $table->string('link')->nullable();
            $table->string('linkmodel');
            $table->boolean('deleted')->default(false);
            $table->integer('created_at')->default(0);
            $table->integer('updated_at')->default(0);
            //$table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('notifications');
    }
}
