<?php

use App\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::withoutForeignKeyConstraints(function () {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('legacy');
                DB::statement('UPDATE users SET legacy = NULL');
            });
        });

        Schema::table('users', function (Blueprint $table) {
            $table->after('email', function (Blueprint $table) {
                $table->string('nick')->nullable();
            });
            $table->rawIndex('(lower(email))', 'users_email_lower_index');
            $table->integer('total')->virtualAs('price * quantity');
            $table->uuid()->nullable();
            $table->foreignIdFor(Team::class, 'owner_team_id')->nullable();
            $table->bigIncrements('sequence');
            $table->someMacro('not_a_column');
            $table->rename('members');
        });

        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'draft')) {
                $table->dropColumn('draft');
            }
            collect(['unused'])->each(function () use ($table) {
                $table->dropColumn('legacy');
            });
        });

        DB::connection('reporting')->statement('VACUUM');
        DB::connection('reporting')->table('stats')->delete();
    }
};
