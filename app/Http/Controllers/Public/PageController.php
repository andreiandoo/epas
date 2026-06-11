<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;

class PageController extends Controller
{
    public function about()   { return view('public.about'); }
    public function contact() { return view('public.contact'); }
}
