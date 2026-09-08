<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a discount definition breaks integrity invariants
 * (value must be positive, percentages bounded, reason required).
 */
class DiscountInvalidException extends RuntimeException {}
