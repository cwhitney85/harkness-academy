<?php

namespace App\Courses;

/**
 * Where a subtopic row came from (spec 16.1.1, ledger D59). A student row
 * stays `student` after it is promoted: promotion lists it, and where it came
 * from does not change.
 */
enum SubtopicOrigin: string
{
    case Catalog = 'catalog';
    case Student = 'student';
}
