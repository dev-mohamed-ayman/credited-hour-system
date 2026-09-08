<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a discount cannot be revoked (invalid state, missing reason,
 * or no remaining balance left to cancel).
 */
class DiscountRevokeException extends RuntimeException {}
