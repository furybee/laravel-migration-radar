<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email', 320)->change();
            $table->renameColumn('name', 'full_name');
            $table->dropColumn(['legacy', 'old']);
            $table->dropTimestamps();
            $table->dropForeign(['company_id']);
            $table->dropUnique('users_email_unique');
            $table->dropConstrainedForeignId('team_id');
            $table->index(['last_name', 'first_name']);
            $table->fullText('bio');
            $table->foreign('owner_id')->references('id')->on('accounts');
        });
    }
};
