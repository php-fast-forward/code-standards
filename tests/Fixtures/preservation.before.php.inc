<?php

/** Copyright (c) 2020 Fixture. */

declare(strict_types=1);

namespace Fixture;

/**
 * Public contract.
 *
 * @author Fixture
 * @package Fixture
 */
class Contract
{
    /** @var string */
    protected string $name = 'example';

    /**
     * @param int $count
     * @return void
     */
    public function accept(int $count): void
    {
        echo $count;
    }

    public function clock(): \DateTime
    {
        return new \DateTime();
    }
}
