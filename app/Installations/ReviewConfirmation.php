<?php

namespace App\Installations;

use App\Models\Installation;

/** A person has looked at the substitutes and missing lines and accepts them. */
final class ReviewConfirmation
{
    public function confirm(Installation $installation): void
    {
        $installation->update(['needs_review' => false, 'review_reason' => null]);
    }
}
