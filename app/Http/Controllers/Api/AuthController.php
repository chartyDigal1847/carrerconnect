<?php

namespace App\Http\Controllers\Api;

use App\Services\Security\RoleCapabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController
{
    public function __construct(private RoleCapabilityService $capabilities) {}

    public function me(Request $request): JsonResponse
    {
        $user = $request->user('faculty') ?? auth('faculty')->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json([
            'id' => $user->id,
            'sso_id' => $user->sso_id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'department' => $user->department,
            'service' => config('careerconnect.service_name'),
            'api_version' => config('careerconnect.api_version'),
            'permissions' => [
                'announcements_create'  => $this->capabilities->can($user, 'announcements.create'),
                'boards_create'         => $this->capabilities->can($user, 'boards.create'),
                'boards_post'           => $this->capabilities->can($user, 'boards.post'),
                'boards_comment'        => $this->capabilities->can($user, 'boards.comment'),
                'resources_create'      => $this->capabilities->can($user, 'resources.create'),
                'messages_send'         => $this->capabilities->can($user, 'messages.send'),
                'analytics'             => $this->capabilities->can($user, 'analytics.*'),
                'moderation'            => $this->capabilities->can($user, 'moderation.*'),
                'opportunities_read'    => $this->capabilities->can($user, 'opportunities.read'),
                'opportunities_apply'   => $this->capabilities->can($user, 'opportunities.apply'),
                'opportunities_manage'  => $this->capabilities->can($user, 'opportunities.manage'),
                'opportunities_approve' => $this->capabilities->can($user, 'opportunities.approve'),
                'opportunities_delete'  => $this->capabilities->can($user, 'opportunities.delete'),
                'opportunities_reports' => $this->capabilities->can($user, 'opportunities.reports'),
            ],
            'capabilities' => config('careerconnect.roles.capabilities.'.$user->role, []),
        ]);
    }
}
