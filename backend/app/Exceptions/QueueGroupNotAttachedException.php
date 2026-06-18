<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * Thrown when an admin tries to detach a queue group that is not attached to the
 * target window (task 043, plan §5.4). Detaching a group the window does not serve
 * is a 404 — there is no such pivot row to remove.
 */
class QueueGroupNotAttachedException extends RuntimeException
{
    public function __construct(string $message = 'That queue group is not attached to this window.')
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
