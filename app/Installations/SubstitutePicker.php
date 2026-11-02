<?php

namespace App\Installations;

/**
 * The substitute rule, kept pure so the API and the benchmark run the same
 * code: an active product of the same family with enough free stock, closest
 * in price; ties go to the lower id so the choice is deterministic.
 *
 * A proposal only: the check flags the installation so a person confirms it.
 */
final class SubstitutePicker
{
    /**
     * @param  iterable<Candidate>  $candidates  products of the same family
     * @param  array<int, true>  $exclude  product ids already in the installation
     */
    public static function pick(Candidate $wanted, int $quantity, iterable $candidates, array $exclude = []): ?Candidate
    {
        $best = null;
        foreach ($candidates as $candidate) {
            if (! self::qualifies($wanted, $quantity, $candidate, $exclude)) {
                continue;
            }
            if ($best === null || self::closer($wanted, $candidate, $best)) {
                $best = $candidate;
            }
        }

        return $best;
    }

    /** @param  array<int, true>  $exclude */
    private static function qualifies(Candidate $wanted, int $quantity, Candidate $candidate, array $exclude): bool
    {
        return $candidate->productId !== $wanted->productId
            && $candidate->familyId === $wanted->familyId
            && $candidate->active
            && $candidate->available >= $quantity
            && ! isset($exclude[$candidate->productId]);
    }

    private static function closer(Candidate $wanted, Candidate $a, Candidate $b): bool
    {
        $da = abs($a->priceCents - $wanted->priceCents);
        $db = abs($b->priceCents - $wanted->priceCents);

        return $da < $db || ($da === $db && $a->productId < $b->productId);
    }
}
