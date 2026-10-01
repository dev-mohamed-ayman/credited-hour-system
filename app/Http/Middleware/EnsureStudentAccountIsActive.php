<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentAccountIsActive
{
    /**
     * Block every portal action for dismissed/graduated students; they may only
     * view the dashboard and the status statement.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $student = $request->user('student');

        if ($student?->isPortalReadOnly()) {
            $message = 'حسابك للعرض فقط ('.$student->status->label().') — لا يمكنك إجراء أي عمليات.';

            if ($request->isMethod('GET') && ! $request->expectsJson()) {
                return redirect()->route('student.dashboard')->with('error', $message);
            }

            abort(403, $message);
        }

        return $next($request);
    }
}
