<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown to roll back a registration whose fees the student's wallet cannot cover.
 */
class InsufficientWalletBalanceException extends RuntimeException {}
