<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * Thrown when an action requires the student to currently hold an active
 * ticket but they do not (e.g. leaving an empty queue — task 009).
 */
class NotInQueueException extends RuntimeException
{
    public function __construct(string $message = 'You do not have an active ticket.')
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
