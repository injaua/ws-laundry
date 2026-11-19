<?php

namespace App\View\Composers;

use App\Models\CashSession;
use App\Models\Cheque;
use App\Models\Complaint;
use App\Models\CourierJob;
use App\Models\Order;
use App\Models\WhatsAppMessage;
use Illuminate\View\View;

/**
 * Badges of the admin sidebar. Counted once per page, and only those the user's role sees.
 */
class AdminNavigationComposer
{
    private ?array $badges = null;

    public function compose(View $view): void
    {
        $view->with("navBadges", $this->badges ??= $this->badges());
    }

    private function badges(): array
    {
        $user = auth()->user();
        $badges = ["chat_unread" => 0, "complaints" => 0, "till" => null, "late_orders" => 0, "cheques_in_hand" => 0, "pickup_requests" => 0, "whatsapp_unread" => 0];

        if (!$user) {
            return $badges;
        }

        $badges["chat_unread"] = $user->unreadChatCount();
        if ($user->isAdmin() || $user->isCashier() || $user->isOwner()) {
            $badges["complaints"] = Complaint::pending()->count();
            $badges["till"] = CashSession::currentFor($user);
            $badges["late_orders"] = Order::late()->count();
            $badges["pickup_requests"] = CourierJob::where("status", "requested")->count();
        }
        if ($user->seesFinance()) {
            $badges["cheques_in_hand"] = Cheque::where("status", "in_hand")->count();
        }
        if ($user->isCashier()) {
            $badges["whatsapp_unread"] = WhatsAppMessage::unread()->count();
        }

        return $badges;
    }
}
