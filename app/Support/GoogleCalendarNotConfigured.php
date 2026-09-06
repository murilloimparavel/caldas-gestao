<?php

namespace App\Support;

use RuntimeException;

final class GoogleCalendarNotConfigured extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Google Calendar is not configured. Set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI before connecting.');
    }
}
