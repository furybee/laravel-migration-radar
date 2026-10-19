<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // migration-radar-ignore rename-column, drop-column -- the column is unused since v2
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('a', 'b');
            $table->dropColumn('c');
        });

        /* migration-radar-ignore */
        Schema::drop('logs');

        // a regular comment
        Schema::drop('sessions');

        Schema::drop('caches'); // migration-radar-ignore drop-table -- trailing
        Schema::drop('jobs');
    }
};
