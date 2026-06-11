<?php

namespace App\Http\Controllers;

use App\Models\Microservice;
use Illuminate\Http\Request;

class DocsController extends Controller
{
    /**
     * Show microservices documentation index
     */
    public function microservicesIndex()
    {
        $microservices = Microservice::active()->get();

        return view('docs.microservices.index', [
            'microservices' => $microservices,
        ]);
    }

    /**
     * Show documentation for a specific microservice
     */
    public function microserviceShow($slug)
    {
        $microservice = Microservice::where('slug', $slug)->firstOrFail();

        // Get related microservices in the same category
        $related = Microservice::where('category', $microservice->category)
            ->where('id', '!=', $microservice->id)
            ->active()
            ->limit(3)
            ->get();

        return view('docs.microservices.show', [
            'microservice' => $microservice,
            'related' => $related,
        ]);
    }
}
