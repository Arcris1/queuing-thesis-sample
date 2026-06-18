<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * Thrown when a check-in (task 014) or location update (task 013) references a
 * ticket that does not exist, is not owned by the authenticated student, or is
 * no longer active. We never reveal whether the number exists for someone else.
 */
class TicketNotFoundException extends RuntimeException
{
    public function __construct(string $message = 'No matching active ticket was found for your account.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return new JsonResponse(
            ['message' => $this->getMessage()],
            Response::HTTP_NOT_FOUND,
        );
    }
}
