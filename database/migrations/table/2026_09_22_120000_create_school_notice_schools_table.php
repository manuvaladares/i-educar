<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_notice_schools', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('school_notice_id');
            $table->foreign('school_notice_id')
                ->references('id')
                ->on('school_notices')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->unsignedSmallInteger('school_id');
            $table->foreign('school_id')
                ->references('cod_escola')
                ->on('pmieducar.escola')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->unique(['school_notice_id', 'school_id']);
        });

        DB::statement('
            INSERT INTO school_notice_schools (school_notice_id, school_id)
            SELECT id, school_id
            FROM school_notices
            WHERE school_id IS NOT NULL
        ');

        Schema::table('school_notices', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropColumn('school_id');
        });
    }

    public function down(): void
    {
        Schema::table('school_notices', function (Blueprint $table) {
            $table->unsignedSmallInteger('school_id')->nullable();
            $table->foreign('school_id')
                ->references('cod_escola')
                ->on('pmieducar.escola')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });

        DB::statement('
            UPDATE school_notices sn
            SET school_id = (
                SELECT sns.school_id
                FROM school_notice_schools sns
                WHERE sns.school_notice_id = sn.id
                ORDER BY sns.id ASC
                LIMIT 1
            )
        ');

        Schema::dropIfExists('school_notice_schools');
    }
};