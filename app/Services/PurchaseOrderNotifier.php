<?php

namespace App\Services;

use App\Mail\LowStockAlert;
use App\Mail\PurchaseOrderApproved;
use App\Mail\PurchaseOrderCreated;
use App\Mail\PurchaseOrderSent;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Notifications\PurchaseOrderNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * One place for every Lab 7 notification. In-app alerts are always written to
 * the database immediately; emails use the queue in production and fall back
 * to delivery inside the request when MAIL_MAILER=log (used for grading).
 */
class PurchaseOrderNotifier
{
    /** Admins and managers can approve; both are alerted on new orders. */
    public function approvers(): Collection
    {
        return User::active()->whereIn('role', ['admin', 'manager'])->get();
    }

    public function notifyCreated(PurchaseOrder $order): void
    {
        Notification::send(
            $this->approvers(),
            new PurchaseOrderNotification(
                $order,
                'created',
                'Purchase Order Awaiting Approval',
                "{$order->reference_number} from {$order->supplier?->company_name} needs your approval."
            )
        );

        $this->sendMail($this->approvers(), new PurchaseOrderCreated($order));
    }

    public function notifyApproved(PurchaseOrder $order): void
    {
        $recipients = User::active()
            ->where(function ($query) use ($order) {
                $query->whereIn('role', ['admin', 'manager'])
                    ->orWhere('id', $order->user_id);
            })
            ->get()
            ->unique('id')
            ->values();

        Notification::send(
            $recipients,
            new PurchaseOrderNotification(
                $order,
                'approved',
                'Purchase Order Approved',
                "{$order->reference_number} was approved and is ready to send."
            )
        );

        if ($order->createdBy) {
            $this->sendMail(collect([$order->createdBy]), new PurchaseOrderApproved($order));
        }
    }

    public function notifySent(PurchaseOrder $order): void
    {
        $recipients = $this->approvers()->push($order->createdBy)->filter()->unique('id')->values();

        Notification::send(
            $recipients,
            new PurchaseOrderNotification(
                $order,
                'sent',
                'Purchase Order Sent',
                "{$order->reference_number} was sent to {$order->supplier?->company_name}."
            )
        );

        if ($order->supplier?->email) {
            $this->sendMail(collect([$order->supplier]), new PurchaseOrderSent($order));
        }
    }

    public function notifyLowStock($products, int $ordersGenerated): void
    {
        $recipients = $this->approvers();

        $this->sendMail($recipients, new LowStockAlert($products, $ordersGenerated));
    }

    /** Send immediately for log/mail testing, queue for real SMTP delivery. */
    protected function sendMail(Collection $recipients, object $mailable): void
    {
        if ($recipients->isEmpty()) {
            return;
        }

        try {
            $mail = Mail::to($recipients->first());

            foreach ($recipients->skip(1) as $recipient) {
                $mail->cc($recipient);
            }

            config('mail.default') === 'log' || config('queue.default') === 'sync'
                ? $mail->send($mailable)
                : $mail->queue($mailable);
        } catch (Throwable $exception) {
            // An email failure must never roll back an approved purchase order.
            report($exception);
        }
    }
}
