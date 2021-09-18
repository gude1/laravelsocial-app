<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateComplaintSuggestReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('complaint_suggest_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('profile_id');
            $table->string('type');
            $table->string('msg')->nullable();
            $table->string('model_name')->nullable();
            $table->string('link')->nullable();
            $table->string('reported_profile_id')->nullable();
            $table->boolean('attended_to')->default(false);
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
        Schema::dropIfExists('complaint_suggest_reports');
    }
}
