<?php

namespace App\Support;

use RuntimeException;

final class GoogleCalendarNotConfigured extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('O Google Agenda não está configurado. Defina GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET e GOOGLE_REDIRECT_URI antes de conectar.');
    }
}
