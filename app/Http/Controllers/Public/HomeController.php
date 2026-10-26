<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;

class HomeController extends Controller
{
    /** core.tixello.com nu mai are site public: doar intrarea în cont și înscrierea. */
    public function index()
    {
        return view('public.gateway');
    }
}
