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

    public ?CartUser $seller = null;
    public ?CartUser $buyer = null;
}
