<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A module-grain quiz belongs to no single course (spec 6.5).
 *
 * `quizzes.course_id` was written to mean "the course whose syllabus declares
 * the concept vocabulary this quiz's tags resolve against", and for the four
 * scopes package 5 could see that was always exactly one course. A module
 * refresher draws on every subtopic the 3.3 rollup points at, across courses
 * and skill levels, and naming one of them would make the column say something
 * untrue rather than say nothing.
 *
 * Null therefore means "more than one course", not "unknown". Every kind that
 * has an anchoring course still carries it, which is what keeps the column
 * worth reading at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table): void {
            $table->foreignId('course_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table): void {
            $table->foreignId('course_id')->nullable(false)->change();
        });
    }
};
