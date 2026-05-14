<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('pesan_chat', function (Blueprint $table) {
            $table->id('id_pesan');
            $table->unsignedBigInteger('id_pengajuan');
            $table->enum('pengirim_role', ['warga', 'rt']);
            $table->text('isi_pesan');
            $table->timestamps(); 

            $table->foreign('id_pengajuan')->references('id_pengajuan')->on('pengajuan')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('pesan_chat');
    }
};