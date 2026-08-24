<?php

namespace App\Http\Controllers;

use App\Actions\Dashboard\BuildDashboardSnapshot;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(BuildDashboardSnapshot $buildDashboardSnapshot): Response
    {
        return Inertia::render('dashboard', [
            'dashboard' => $buildDashboardSnapshot->handle(),
        ]);
    }
}
