<?php

namespace Tests\Feature\Errors;

use App\Exceptions\BookingException;
use App\Exceptions\InvalidTransitionException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * ERR-05 dan F6-R7/F6-R10: `BookingException` (dan `InvalidTransitionException`) adalah penolakan bisnis. HTML dialihkan
 * dengan flash `error` dan input tanpa field sensitif, JSON dijawab 422 `{message, code}`, dan keduanya tidak dilaporkan.
 */
class BookingExceptionHandlerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->post('/_uji/booking-penuh', fn () => throw BookingException::make(BookingException::QUOTA_FULL));
        Route::middleware('web')->post('/_uji/booking-transisi', fn () => throw InvalidTransitionException::make('STALE_STATUS'));
    }

    /** @return array<string, string> */
    private function payload(): array
    {
        return [
            'nama' => 'Budi',
            'payment_type' => 'bpjs',
            'password' => 'RahasiaUji1',
            'password_confirmation' => 'RahasiaUji1',
            'current_password' => 'LamaUji1',
            'bpjs_number' => '0001234567890',
            'policy_number' => 'POL-77-AB',
            'payment_reference' => 'PAY-12345',
        ];
    }

    public function test_html_requests_are_redirected_with_the_message_and_without_sensitive_input(): void
    {
        $this->from('/_uji/form')->post('/_uji/booking-penuh', $this->payload())
            ->assertRedirect('/_uji/form')
            ->assertSessionHas('error', __('booking.errors.QUOTA_FULL'))
            ->assertSessionHas('_old_input.nama', 'Budi')
            ->assertSessionHas('_old_input.payment_type', 'bpjs')
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation')
            ->assertSessionMissing('_old_input.current_password')
            ->assertSessionMissing('_old_input.bpjs_number')
            ->assertSessionMissing('_old_input.policy_number')
            ->assertSessionMissing('_old_input.payment_reference');
    }

    public function test_the_response_is_never_a_server_error_or_a_405_redirect_target(): void
    {
        $response = $this->from('/_uji/form')->post('/_uji/booking-penuh', $this->payload());

        $this->assertSame(302, $response->getStatusCode());
    }

    public function test_json_requests_get_a_422_with_message_and_code(): void
    {
        $this->postJson('/_uji/booking-penuh', $this->payload())
            ->assertStatus(422)
            ->assertExactJson(['message' => __('booking.errors.QUOTA_FULL'), 'code' => 'QUOTA_FULL']);
    }

    public function test_invalid_transition_exceptions_follow_the_same_pipeline(): void
    {
        $this->from('/_uji/form')->post('/_uji/booking-transisi', $this->payload())
            ->assertRedirect('/_uji/form')
            ->assertSessionHas('error', __('booking.transitions.STALE_STATUS'));

        $this->postJson('/_uji/booking-transisi', $this->payload())
            ->assertStatus(422)
            ->assertExactJson(['message' => __('booking.transitions.STALE_STATUS'), 'code' => 'STALE_STATUS']);
    }

    public function test_booking_exceptions_are_not_reported_but_other_exceptions_are(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        $this->assertFalse($handler->shouldReport(BookingException::make(BookingException::SYSTEM_BUSY)));
        $this->assertFalse($handler->shouldReport(InvalidTransitionException::make('ALREADY_FINAL')));
        $this->assertTrue($handler->shouldReport(new RuntimeException('galat sistem')));
    }
}
