<?php

namespace App\Support;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CalendarConflictException extends ConflictHttpException {}
