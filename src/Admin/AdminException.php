<?php

declare(strict_types=1);

namespace Qoliber\Trident\Admin;

/**
 * A refused administration request (bad input, missing confirmation); the
 * controller answers it with 400 and the message.
 */
final class AdminException extends \RuntimeException
{
}
