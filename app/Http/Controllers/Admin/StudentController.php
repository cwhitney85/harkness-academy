<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The students an admin may view as (spec 13.4). A stand-in for 17.1's Users
 * screen, which replaces it: 17.1.4 starts viewing-as from there.
 *
 * Admins are not listed, because viewing another admin is a WONT (13.4.7).
 */
class StudentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/students', [
            'students' => User::query()
                ->where('role', Role::Student)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'plan', 'created_at'])
                ->map(fn (User $student): array => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'email' => $student->email,
                    'plan' => $student->plan,
                    'joined' => $student->created_at?->format('j M Y'),
                ]),
        ]);
    }
}
