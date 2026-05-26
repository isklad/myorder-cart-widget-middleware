<?php
declare(strict_types=1);

namespace Isklad\MyorderCartWidgetMiddleware\Model;

final class Cart
{
    public string $orderExternalId = '';

    /**
     * @var Product[]
     */
    public array $products = [];
    public string $currency = '';
    public int $weight = 0;
    public ?Person $person = null;
    public ?Address $address = null;
}
