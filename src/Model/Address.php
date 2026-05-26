<?php
declare(strict_types=1);

namespace Isklad\MyorderCartWidgetMiddleware\Model;

final class Address
{
    public string $name = '';
    public string $company = '';
    public string $businessId = '';
    public string $taxId = '';
    public string $vatId = '';
    public string $street = '';
    public string $streetNumber = '';
    public string $city = '';
    public string $postalCode = '';
}
