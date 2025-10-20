<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('landing_testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('profile_img')->nullable();
            $table->string('user_name');
            $table->text('comment');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_testimonials');
    }
};


