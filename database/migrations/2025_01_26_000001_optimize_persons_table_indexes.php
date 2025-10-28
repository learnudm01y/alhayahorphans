<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class OptimizePersonsTableIndexes extends Migration
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
		// Intentionally left blank. Index changes were applied elsewhere or
		// will be handled manually to avoid accidental duplicate index creation.
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
