<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * Thrown when an admin tries to attach a queue group to a window in a different
 * office (task 043, plan §5.4). A window may only serve queue groups belonging to
 * its own office, so cross-office attaches are rejected as unprocessable.
 */
class CrossOfficeAttachException extends RuntimeException
{
    public function __construct(string $message = 'A queue group can only be attached to a window in the same office.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return new JsonResponse(
            ['message' => $this->getMessage()],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }
}
