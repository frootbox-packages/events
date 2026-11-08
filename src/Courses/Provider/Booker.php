<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses\Provider;

/**
 * The person who books and gets the invoice.
 */
final readonly class Booker
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public ?Salutation $salutation = null,
        public ?string $company = null,
        public ?string $street = null,
        public ?string $houseNumber = null,
        public ?string $addition = null,
        public ?string $postalCode = null,
        public ?string $city = null,
        public ?string $country = null,
        public ?string $phone = null,
    ) {}
}
