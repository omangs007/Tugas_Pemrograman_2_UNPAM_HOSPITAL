<?php

namespace App\Http\Middleware;

use App\Enums\AccountType;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias `account` (11 §7.1): `account:patient` atau `account:staff`.
 *
 * Pasien yang membuka URL admin ⇒ 403; staf yang membuka portal pasien ⇒ dialihkan ke dasbor admin.
 */
class EnsureAccountType
{
    public function handle(Request $request, Closure $next, string $type): Response
    {
        $required = AccountType::from($type);
        $user = $request->user();

        if ($user !== null && $user->account_type === $required) {
            return $next($request);
        }

        if ($required === AccountType::Staff) {
            Log::notice('Akses area admin oleh akun non-staf ditolak', [
                'user_id' => $user?->id,
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
            ]);

            abort(403, 'Anda tidak memiliki izin untuk mengakses halaman ini.');
        }

        if ($request->expectsJson()) {
            abort(403);
        }

        return redirect()->route('admin.dashboard');
    }
}
