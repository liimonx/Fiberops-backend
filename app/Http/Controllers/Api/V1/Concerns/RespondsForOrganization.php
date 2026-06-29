<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\User;
use Illuminate\Http\JsonResponse;

trait RespondsForOrganization
{
    protected function organizationId(): int
    {
        /** @var User $user */
        $user = auth()->user();

        return (int) $user->organization_id;
    }

    protected function notFound(string $message): JsonResponse
    {
        return response()->json(['error' => $message], 404);
    }

    protected function forbidden(string $message = 'Insufficient permissions.'): JsonResponse
    {
        return response()->json(['error' => $message], 403);
    }

    protected function ensureCanWrite(): ?JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $user->canWrite()) {
            return $this->forbidden();
        }

        return null;
    }
}
