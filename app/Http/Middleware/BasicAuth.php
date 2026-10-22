<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $username = (string) config('status.auth.username');
        $password = (string) config('status.auth.password');

        // Closed by default: without configured credentials, nobody gets in.
        $authorized = $username !== '' && $password !== ''
            && hash_equals($username, (string) $request->getUser())
            && hash_equals($password, (string) $request->getPassword());

        if (! $authorized) {
            return response('Authentication required', 401, ['WWW-Authenticate' => 'Basic realm="Laravel Status"']);
        }

        return $next($request);
    }
}
