<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportUploadFileInspector
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if(count($request->allFiles()) > 1) {
            abort(400, 'Anda hanya bisa menggunggah 1 file');
        }

        return $next($request);
    }
}
