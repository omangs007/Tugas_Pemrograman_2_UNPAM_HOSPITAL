<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Validation\ValidationException;

/**
 * Pembantu tes untuk memvalidasi Form Request tanpa melewati route (pola ListFilterRequestTest).
 * Dipakai bersama InteractsWithStaff: pengguna bawaan adalah Admin Konten yang diizinkan `schedules.manage`.
 */
trait InteractsWithFormRequests
{
    private ?User $formRequestUser = null;

    /**
     * Membangun Form Request lengkap dengan pengguna dan parameter route (model hasil binding).
     *
     * @param  class-string<FormRequest>  $class
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $routeParameters
     */
    protected function makeFormRequest(string $class, array $data, ?User $user = null, array $routeParameters = [], string $method = 'POST'): FormRequest
    {
        $user ??= $this->formRequestUser ??= $this->createStaff('content-admin');

        $request = $class::create('/_uji/form-request', $method, $data);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $request->setUserResolver(fn () => $user);

        $uri = '/_uji/form-request';

        foreach (array_keys($routeParameters) as $name) {
            $uri .= '/{'.$name.'}';
        }

        $route = new Route($method, $uri, fn () => null);
        $route->bind($request);

        foreach ($routeParameters as $name => $value) {
            $route->setParameter($name, $value);
        }

        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    /**
     * Menjalankan validasi dan mengembalikan galat per field (kosong bila lolos). Penolakan otorisasi
     * dilempar sebagai AuthorizationException.
     *
     * @param  class-string<FormRequest>  $class
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $routeParameters
     * @return array<string, list<string>>
     */
    protected function validationErrors(string $class, array $data, ?User $user = null, array $routeParameters = [], string $method = 'POST'): array
    {
        try {
            $this->makeFormRequest($class, $data, $user, $routeParameters, $method)->validateResolved();
        } catch (ValidationException $exception) {
            return $exception->errors();
        }

        return [];
    }
}
