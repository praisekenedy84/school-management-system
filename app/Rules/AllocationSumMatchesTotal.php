<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Enforces the core finance invariant (RULES.md §6 / docs/prd-financial-module.md
 * §7): the sum of allocation[].amount must EXACTLY equal the slip's
 * total_amount. Computed with BCMath at scale 2 — never float — because
 * money is DECIMAL(15,2) and float summation (e.g. 0.1 + 0.2) would produce
 * spurious mismatches or, worse, spurious matches.
 *
 * This rule is attached to the `allocation` attribute; it reads total_amount
 * from the full request payload. It defends against: non-array allocation,
 * missing/non-numeric line amounts, and sum != total.
 */
class AllocationSumMatchesTotal implements ValidationRule
{
    public function __construct(private readonly mixed $totalAmount) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || $value === []) {
            $fail('The allocation must contain at least one line.');

            return;
        }

        $sum = '0';

        foreach ($value as $line) {
            if (! is_array($line) || ! isset($line['amount']) || ! is_numeric($line['amount'])) {
                $fail('Each allocation line must include a numeric amount.');

                return;
            }

            // Normalize each line to 2dp before summing so a value like
            // "100.005" can't smuggle sub-cent precision past the check.
            $sum = bcadd($sum, $this->normalize((string) $line['amount']), 2);
        }

        if (! is_numeric($this->totalAmount)) {
            // total_amount has its own `numeric` rule; let that report it.
            return;
        }

        $total = $this->normalize((string) $this->totalAmount);

        if (bccomp($sum, $total, 2) !== 0) {
            $fail("The allocation amounts ({$sum}) must sum exactly to the slip total ({$total}).");
        }
    }

    private function normalize(string $number): string
    {
        // bcadd/bccomp truncate (not round) beyond scale, so explicitly
        // round to 2dp first to match DECIMAL(15,2) storage semantics.
        return number_format((float) $number, 2, '.', '');
    }
}
