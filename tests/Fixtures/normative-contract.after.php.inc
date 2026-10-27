<?php

declare(strict_types=1);

namespace Fixture;

/** Keeps semantic requirements visible at the interface boundary. */
interface NormativeContract
{
    /**
     * Returns the supplied value as a one-element list.
     *
     * Implementations MUST preserve the supplied value and MUST NOT accept negative input.
     *
     * @param int $value Nonnegative value whose identity the result retains.
     * @return list<int> Exactly one unchanged value, without normalization.
     * @throws \InvalidArgumentException When the supplied value is negative.
     */
    public function values(int $value): array;
}

/** Supplies the inherited contract without duplicating its requirements. */
final class NormativeImplementation implements NormativeContract
{
    /** {@inheritDoc} */
    public function values(int $value): array
    {
        if ($value < 0) {
            throw new \InvalidArgumentException('Value must not be negative.');
        }

        return [$value];
    }
}
