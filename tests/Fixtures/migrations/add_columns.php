<?php

use App\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference');
            $table->foreignId('user_id')->constrained();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('code');
            $table->string('nickname')->nullable()->after('name');
            $table->boolean('is_active')->default(true);
            $table->uuid('public_id')->default(DB::raw('gen_random_uuid()'));
            $table->timestamp('seen_at')->useCurrent();
            $table->integer('total')->storedAs('price * quantity');
            $table->string('email')->nullable(false)->unique();
            $table->foreignId('company_id')->nullable()->constrained('companies');
            $table->foreignIdFor(Team::class);
            $table->timestamps();
            $table->softDeletes('archived_at');
            $table->nullableMorphs('owner');
            $table->comment('Users of the application');
        });
    }
};
