<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE users ADD COLUMN x INT');
        DB::unprepared($this->sql());
        DB::table('users')->whereNull('status')->update(['status' => 'active']);
        User::query()->where('active', false)->delete();
        User::chunkById(500, function ($users) {
            DB::update('UPDATE users SET flag = 1');
        });
        DB::table('users')->where('id', 1)->first();
    }

    private function sql(): string
    {
        return 'SELECT 1';
    }
};
