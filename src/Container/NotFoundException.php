<?php declare(strict_types=1);
/**
 * Starlit App.
 *
 * @copyright Copyright (c) 2016 Starweb AB
 * @license   BSD 3-Clause
 */

namespace Starlit\App\Container;

use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends \InvalidArgumentException implements NotFoundExceptionInterface
{
}
