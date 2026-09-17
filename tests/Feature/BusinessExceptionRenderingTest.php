<?php

namespace Tests\Feature;

use App\Exceptions\BusinessInputException;
use App\Exceptions\BusinessRuleException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Tests\TestCase;

class BusinessExceptionRenderingTest extends TestCase
{
    public function test_business_rule_exception_is_rendered_as_conflict_json_for_v1(): void
    {
        $response = app(ExceptionHandler::class)->render(
            Request::create('/v1/orders', 'POST'),
            new BusinessRuleException('Insufficient inventory.'),
        );

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame([
            'message' => 'Insufficient inventory.',
            'code' => 'BUSINESS_RULE_VIOLATION',
        ], json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_business_input_exception_is_rendered_as_unprocessable_json_for_v1(): void
    {
        $response = app(ExceptionHandler::class)->render(
            Request::create('/v1/orders', 'POST'),
            new BusinessInputException('Invalid quantity.'),
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('INVALID_BUSINESS_INPUT', json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR)['code']);
    }
}
