<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProfileSettingsTable extends Migration
{
  /**
  * Run the migrations.
  *
  * @return void
  */
  public function up() {
    Schema::create('profile_settings', function (Blueprint $table) {
      $table->bigIncrements('id');
      $table->string('profile_id')->unique();
      $table->longText('blocked_profiles')->default(json_encode([]));
        $table->longText('muted_profiles')->default(json_encode([]));
          $table->timestamps();
        });
    }

      /**
      * Reverse the migrations.
      *
      * @return void
      */
      public function down() {
        Schema::dropIfExists('profile_settings');
      }
    }