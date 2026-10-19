<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const TABLE = 'invoices';
    private const PREFIX = 'billing_';

    public function up(): void
    {
        Schema::rename('bills', self::TABLE);
        Schema::dropIfExists(self::PREFIX . 'drafts');
        Schema::drop('payments');
        Schema::dropColumns(self::TABLE, ['notes']);
        Schema::connection('reporting')->table('stats', fn (Blueprint $t) => $t->dropColumn('views'));

        foreach (['a', 'b'] as $suffix) {
            Schema::table('shard_' . $suffix, function (Blueprint $table) {
                $table->dropColumn('tmp');
            });
        }
    }

    public function down(): void
    {
        Schema::drop(self::TABLE);
    }
};
