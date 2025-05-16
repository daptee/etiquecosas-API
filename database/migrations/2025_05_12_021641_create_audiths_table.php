<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAudithsTable extends Migration
{
    public function up()
    {
        Schema::create('audiths', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('userId')->nullable();
            $table->text('request');
            $table->text('params');
            $table->text('response');
            $table->timestamp('datetime')->useCurrent();
            $table->foreign('userId')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('audiths');
    }
}
