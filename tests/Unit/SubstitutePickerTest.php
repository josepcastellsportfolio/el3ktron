<?php

use App\Installations\Candidate;
use App\Installations\SubstitutePicker;

$wanted = new Candidate(1, familyId: 7, priceCents: 1000, available: 0, active: true);

it('picks the active same-family product with stock closest in price', function () use ($wanted) {
    $pick = SubstitutePicker::pick($wanted, 5, [
        new Candidate(2, 7, 1500, 50, true),
        new Candidate(3, 7, 900, 50, true),   // closest
        new Candidate(4, 8, 1000, 50, true),  // other family
        new Candidate(5, 7, 1000, 50, false), // discontinued
        new Candidate(6, 7, 1000, 4, true),   // not enough stock
    ]);

    expect($pick?->productId)->toBe(3);
});

it('skips products already in the installation', function () use ($wanted) {
    $pick = SubstitutePicker::pick($wanted, 1, [new Candidate(3, 7, 1000, 9, true)], exclude: [3 => true]);

    expect($pick)->toBeNull();
});

it('breaks price ties by lower id, so the choice is stable', function () use ($wanted) {
    $pick = SubstitutePicker::pick($wanted, 1, [new Candidate(9, 7, 1100, 9, true), new Candidate(4, 7, 900, 9, true)]);

    expect($pick?->productId)->toBe(4);
});
