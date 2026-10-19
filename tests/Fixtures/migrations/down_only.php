<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function down(): void
    {
        Schema::dropIfExists('legacy');
    }
};
