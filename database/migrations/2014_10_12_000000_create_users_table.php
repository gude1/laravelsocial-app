<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    Schema::create('users', function (Blueprint $table) {
      $table->bigIncrements('id');
      $table->string('userid')->unique();
      $table->string('name', 50);
      $table->string('username', 15)->unique();
      $table->enum('gender', ['male', 'female', 'false'])->default('false');
      $table->string('email', 50)->unique();
      $table->string('phone', 11)->unique();
      $table->integer('last_login')->default(0);
      $table->string('password');
      $table->boolean('approved')->default(true);
      $table->boolean('verifed')->default(false);
      $table->mediumText('device_token')->nullable();
      $table->boolean('suspended')->default(false);
      $table->boolean('deleted')->default(false);
      $table->timestamp('email_verified_at')->nullable();
      $table->rememberToken();
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
    Schema::dropIfExists('users');
  }
}
