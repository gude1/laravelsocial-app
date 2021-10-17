<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddToProfiles extends Migration
{
  /**
  * Run the migrations.
  *
  * @return void
  */
  public function up() {
    Schema::table('profiles', function (Blueprint $table) {
      /*$table->boolean('is_campus')->default(true);*/
    });
  }

  /**
  * Reverse the migrations.
  *
  * @return void
  */
  public function down() {
    Schema::table('profiles', function (Blueprint $table) {
      //
    });
  }
}
