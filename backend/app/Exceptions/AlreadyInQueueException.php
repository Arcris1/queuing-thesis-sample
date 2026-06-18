<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * Thrown when a student tries to join a queue group they already hold an
 * active ticket in (one active ticket per queue group per day — task 008).
 */
class AlreadyInQueueException extends RuntimeException
{
    public function __construct(string $message = 'You already have an active ticket in this queue.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return new JsonResponse(
            ['message' => $this->getMessage()],
            Response::HTTP_CONFLICT,
        );
    }
}
