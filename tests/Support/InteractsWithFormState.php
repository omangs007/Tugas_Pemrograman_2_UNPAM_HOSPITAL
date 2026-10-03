<?php

namespace Tests\Support;

trait InteractsWithFormState
{
    /**
     * Mensimulasikan input lama (hasil flash) agar helper old() di komponen Blade membacanya.
     * old() hanya membaca sesi bila request memiliki sesi; pada tes view tanpa HTTP, sesi dipasang manual.
     *
     * @param  array<string, mixed>  $input
     */
    protected function withOldInput(array $input): void
    {
        $session = $this->app['session.store'];

        $this->app['request']->setLaravelSession($session);
        $session->put('_old_input', $input);
    }
}
