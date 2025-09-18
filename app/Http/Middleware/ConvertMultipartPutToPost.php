<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ConvertMultipartPutToPost
{
    public function handle(Request $request, Closure $next)
    {
        $method = strtoupper($request->getMethod());
        $contentType = $request->header('Content-Type', '');

        if (in_array($method, ['PUT', 'PATCH']) && str_starts_with($contentType, 'multipart/form-data')) {
            // Preserve original method and convert to POST so PHP parses form-data
            $original = $method;
            $request->setMethod('POST');
            // Add method override so routing semantics are preserved if needed
            $request->request->add(['_method' => $original]);
        }

        return $next($request);
    }
}




