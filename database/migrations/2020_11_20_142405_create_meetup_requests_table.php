<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMeetupRequestsTable extends Migration
{
  /**
  * Run the migrations.
  *
  * @return void
  */
  public function up() {
    Schema::create('meetup_requests', function (Blueprint $table) {
      $table->bigIncrements('id');
      $table->string('request_msg')->nullable();
      $table->string('request_addr')->nullable();
      $table->integer('request_location')->nullable();
      $table->string('request_category');
      $table->string('request_mood')->default('😄');
        $table->string('request_id')->unique();
        $table->string('requester_id');
        $table->longText('responders_ids')->default(json_encode([]));
          $table->integer('expires_at');
          $table->boolean('deleted')->default(false);
            $table->integer('created_at');
            $table->integer('updated_at');
          });
      }

      /**
      * Reverse the migrations.
      *
      * @return void
      */
      public function down() {
        Schema::dropIfExists('meetup_requests');
      }
    }