<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class DropLegacyTables extends Migration
{
    public function up()
    {
        Schema::dropIfExists('legacy');
    }
}
