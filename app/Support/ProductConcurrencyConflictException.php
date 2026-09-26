<?php

namespace App\Support;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ProductConcurrencyConflictException extends ConflictHttpException {}
