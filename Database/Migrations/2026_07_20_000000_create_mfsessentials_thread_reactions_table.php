<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMfsessentialsThreadReactionsTable extends Migration
{
    public $timestamps = false;

    /**
     * @return void
     */
    public function up()
    {
        Schema::create('mfsessentials_thread_reactions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('thread_id')->unsigned();
            $table->integer('user_id')->unsigned();
            $table->string('emoji', 16);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['thread_id', 'user_id', 'emoji'], 'uniq_thread_user_emoji');
            $table->index('thread_id', 'idx_thread_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mfsessentials_thread_reactions');
    }
}
