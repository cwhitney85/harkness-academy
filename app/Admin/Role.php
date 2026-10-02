<?php

namespace App\Admin;

/**
 * What a person may administer (spec 13.1, ledger D61).
 *
 * Apart from plan (how long their courses are) and skill level (how they are
 * taught): none of the three reads another (4.5.6).
 */
enum Role: string
{
    case Student = 'student';
    case Admin = 'admin';
}
