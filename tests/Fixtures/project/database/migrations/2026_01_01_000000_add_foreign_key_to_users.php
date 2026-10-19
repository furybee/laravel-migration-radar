<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->constrained();
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->constrained();
        });
    }
};
