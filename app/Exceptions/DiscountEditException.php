<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a discount cannot be edited (not active, or already applied to a ticket).
 */
class DiscountEditException extends RuntimeException {}
