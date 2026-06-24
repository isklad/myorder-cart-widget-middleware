<?php
declare(strict_types=1);

namespace Isklad\MyorderCartWidgetMiddleware\Model;

final class CartUser
{
    public ?Person $person = null;
    public ?Address $address = null;
}
