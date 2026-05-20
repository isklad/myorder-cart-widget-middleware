<?php
declare(strict_types=1);

namespace Isklad\MyorderCartWidgetMiddleware\Cart;

final class Cart
{
    /**
     * @var CartProduct[]
     */
    public array $products = [];
    public string $currency = '';
    public int $weight = 0;
}
