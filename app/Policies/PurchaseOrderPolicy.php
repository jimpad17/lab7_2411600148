<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    /** Admins bypass every check (superuser behaviour). */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    /** Only the creator can edit, and only while the order is still a draft. */
    public function update(User $user, PurchaseOrder $order): bool
    {
        return $order->canBeEdited() && (int) $order->user_id === (int) $user->id;
    }

    public function delete(User $user, PurchaseOrder $order): bool
    {
        // Only administrators may remove/cancel purchase orders, and drafts
        // are the only orders eligible for a true soft delete.
        return $user->isAdmin() && $order->canBeEdited();
    }

    /** Managers/admins approve pending orders; high-value orders need an admin. */
    public function approve(User $user, PurchaseOrder $order): bool
    {
        if (! $order->canBeApproved() || ! $user->canApprovePurchaseOrders()) {
            return false;
        }

        return ! $order->requires_second_approval || $user->isAdmin();
    }

    public function reject(User $user, PurchaseOrder $order): bool
    {
        return $order->canBeApproved() && $user->canApprovePurchaseOrders();
    }

    public function send(User $user, PurchaseOrder $order): bool
    {
        return $order->status === PurchaseOrder::STATUS_APPROVED
            && $user->canApprovePurchaseOrders();
    }

    /** Warehouse staff and admins record receipt of approved/sent orders. */
    public function receive(User $user, PurchaseOrder $order): bool
    {
        return $order->canBeReceived() && $user->canReceivePurchaseOrders();
    }
}
