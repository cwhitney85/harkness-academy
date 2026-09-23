<?php

namespace App\Courses;

/**
 * Where a generation request stands (spec 16.2.11, ledger D68).
 *
 * A request is kept once it is over, so its row no longer answers "is this
 * being made?" by existing. At most one per (course, kind) is `open`.
 */
enum GenerationState: string
{
    case Open = 'open';

    /** What was asked for exists. */
    case Landed = 'landed';

    /** The generator gave up, having written nothing; the row says why. */
    case Failed = 'failed';

    /** Outlived its kind's patience, and a new press replaced it. */
    case Expired = 'expired';

    /** Its course was removed while it was out (16.2.8). */
    case Cancelled = 'cancelled';
}
