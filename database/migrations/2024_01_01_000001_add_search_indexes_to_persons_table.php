<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSearchIndexesToPersonsTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * This migration file was empty and caused Laravel to fail when
	 * trying to instantiate the expected class. Provide a no-op
	 * implementation so the migrator can load it safely.
	 *
	 * @return void
	 */
	public function up()
	{
		// Intentionally left blank. Index changes are handled in other migrations.
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		// No-op
	}
}
