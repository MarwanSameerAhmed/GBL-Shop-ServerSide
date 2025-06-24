<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('item_name');
            $table->string('item_processed_name');
            $table->string('item_en_name');
            $table->text('item_description');
            $table->string('item_short_description');
            $table->string('item_en_short_description');
            $table->text('item_en_description');
            $table->double('price')->nullable();
            $table->unsignedBigInteger('parent_id');
            $table->timestamps();

            $table->foreign('parent_id')
                ->references('id')
                ->on('categories');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
