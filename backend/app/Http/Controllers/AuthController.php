<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\LoginData;
use App\DTOs\RegisterData;
use App\Http\Requests\Auth\FcmTokenRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\AuthTokenResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $token = $this->authService->register(RegisterData::fromRequest($request));

        return AuthTokenResource::make($token)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $token = $this->authService->login(LoginData::fromRequest($request));

        return AuthTokenResource::make($token)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();

        return UserResource::make($user)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * POST /api/me/fcm-token — store the caller's FCM device token for push (task 020).
     */
    public function fcmToken(FcmTokenRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();

        $user = $this->authService->updateFcmToken($user, $request->fcmToken());

        return UserResource::make($user)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
