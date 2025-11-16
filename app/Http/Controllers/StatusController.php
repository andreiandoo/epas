<?php

namespace App\Http\Controllers;

use App\Services\Health\HealthCheckService;
use Illuminate\Http\Request;

class StatusController extends Controller
{
    public function __construct(protected HealthCheckService $healthCheckService)
    {
    }

    /**
     * Public status page
     */
    public function index()
    {
        $health = $this->healthCheckService->checkAll();

        return view('status', [
            'health' => $health,
        ]);
    }
}
