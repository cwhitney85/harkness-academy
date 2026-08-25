<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /**
     * `$this->authorize(...)` for every controller.
     *
     * Added by the package 5 session: the lesson routes are the first in the
     * app to guard a resource that belongs to one student (spec 5.2.6's
     * session auth), and a controller that has to remember to import the trait
     * is a controller that will one day forget.
     */
    use AuthorizesRequests;
}
