<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            UserRole::Agent,
            UserRole::Admin,
        ], true);
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if (in_array($user->role, [
            UserRole::Agent,
            UserRole::Admin,
        ], true)) {
            return true;
        }

        return $ticket->created_by_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        if (in_array($user->role, [
            UserRole::Agent,
            UserRole::Admin,
        ], true)) {
            return true;
        }

        return $ticket->created_by_id === $user->id;
    }

    public function assign(User $user, Ticket $ticket): bool
    {
        return in_array($user->role, [
            UserRole::Agent,
            UserRole::Admin,
        ], true);
    }

    public function changePriority(User $user, Ticket $ticket): bool
    {
        return in_array($user->role, [
            UserRole::Agent,
            UserRole::Admin,
        ], true);
    }

    public function changeStatus(User $user, Ticket $ticket): bool
    {
        return match ($user->role) {
            UserRole::Requester => $ticket->created_by_id === $user->id
                && $ticket->status === \App\Enums\TicketStatus::Resolved,

            UserRole::Agent,
            UserRole::Admin => true,

            default => false,
        };
    }
}
