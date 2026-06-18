<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * Thrown when a window serve/skip/recall action is attempted but the window has
 * no open assignment to act on (task 021).
 */
class NoActiveAssignmentException extends RuntimeException
{
    public function __construct(string $message = 'This window has no ticket currently being served.')
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
