<?php

namespace App\Admin\Exceptions;

use RuntimeException;

/**
 * A removal that cannot be restored yet (spec 16.2.6). The message is the
 * admin's: it names what stands in the way.
 */
final class RestoreRefused extends RuntimeException {}
