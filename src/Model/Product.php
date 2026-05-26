<?php
declare(strict_types=1);

namespace Isklad\MyorderCartWidgetMiddleware\Model;

final class Product
{
    public string $id = '';
    public string $name = '';
    public string $imageUrl = '';
    public string $description = '';
    public float $price = 0;
    public int $quantity = 0;
    public ?int $leadTime = null;
}
