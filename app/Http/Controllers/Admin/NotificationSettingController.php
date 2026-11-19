<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MessageTemplate;
use App\Models\SiteSetting;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\AutomaticNotifications;
use App\Services\WhatsApp\WebSessionGateway;
use App\Services\WhatsApp\WhatsAppChannels;
use App\Services\WhatsApp\WhatsAppException;
use App\Services\WhatsApp\WhatsAppSettings;
use App\Services\WhatsApp\WhatsAppWebService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** WhatsApp channels (incl. the QR connection), automatic notifications and message templates. */
class NotificationSettingController extends Controller
{
    public function index(AutomaticNotifications $notifications, WhatsAppChannels $channels): View
    {
        $events = collect(AutomaticNotifications::EVENTS)->map(fn ($defaults, $event) => (object) [
            "key" => $event,
            "enabled" => $notifications->enabled($event),
            "template_id" => (int) SiteSetting::get("notify_{$event}_template_id"),
            "days" => in_array($event, AutomaticNotifications::SCHEDULED, true) ? $notifications->days($event) : null,
            "sent_30d" => WhatsAppMessage::where("notification_key", "like", "{$event}:%")->where("created_at", ">=", now()->subDays(30))->where("status", "!=", "failed")->count(),
            "failed_30d" => WhatsAppMessage::where("notification_key", "like", "{$event}:%")->where("created_at", ">=", now()->subDays(30))->where("status", "failed")->count(),
        ]);

        return view("admin.notifications.index", [
            "events" => $events,
            "templates" => MessageTemplate::orderBy("name")->get(),
            "conversationChannel" => $channels->conversations()->channel(),
            "drivers" => WhatsAppSettings::DRIVERS,
            "notificationsSetting" => (string) (SiteSetting::get("whatsapp_notifications_driver") ?? (config("whatsapp.notifications_driver") ?? "")),
            "notificationChannel" => $channels->notificationsDriver(),
            "usesCloud" => in_array("cloud", [$channels->conversations()->channel(), $channels->notificationsDriver()], true),
            "configured" => filled(config("whatsapp.cloud.phone_number_id")) && filled(config("whatsapp.cloud.access_token")),
            "webTokenOk" => strlen((string) config("whatsapp.web.token")) >= 16,
            "webhookSigned" => filled(config("whatsapp.cloud.app_secret")),
            "webhookUrl" => route("webhooks.whatsapp"),
            "recentFailures" => WhatsAppMessage::with("customer:id,name")->where("direction", "out")->where("status", "failed")
                ->latest("id")->limit(10)->get(),
        ]);
    }

    /**
     * Channel of the conversations (log / official API / a number linked by QR code) and of the
     * automatic notifications. Choosing the QR code also creates the secret shared with the service.
     */
    public function updateChannels(Request $request, WhatsAppWebService $service): RedirectResponse
    {
        $data = $request->validate([
            "driver" => ["required", Rule::in(WhatsAppSettings::DRIVERS)],
            // The unofficial QR connection never sends automatic notifications (the number would be blocked).
            "notifications_driver" => ["nullable", Rule::in(WhatsAppSettings::NOTIFICATION_DRIVERS)],
        ]);

        WhatsAppSettings::save($data["driver"], (string) ($data["notifications_driver"] ?? ""));

        // QR code chosen: start the service right away (first run installs it); otherwise stop it.
        $message = __("app.wa_channels_saved");
        if ($service->enabled()) {
            if ($data["driver"] === "web") {
                $message .= " " . __("app.wa_web_autostart_" . $service->start());
            } else {
                $service->stop();
            }
        }

        return redirect()->route("admin.notifications.index")->with("success", $message);
    }

    /** "Start service" button, when the QR service is not running. */
    public function webStart(WhatsAppWebService $service): RedirectResponse
    {
        abort_unless(config("whatsapp.driver") === "web", 404);
        $result = $service->start();

        return back()->with($result === "failed" ? "error" : "success", __("app.wa_web_autostart_" . $result));
    }

    /** State of the QR connection, polled by the settings page while the QR is shown. */
    public function webStatus(WhatsAppWebService $service): JsonResponse
    {
        $status = $this->webGateway()?->status() ?? ["status" => "disabled", "qr" => null, "me" => null];

        // Not answering yet: still installing / booting, or failed (the log says why).
        if ($status["status"] === "offline") {
            if ($service->isBooting()) {
                $status["status"] = "booting";
            }
            $status["log"] = $service->logTail();
            $status["can_start"] = !$service->isBooting();
        }

        return response()->json($status);
    }

    /** Unlink the phone from the QR connection (e.g. to link another number). */
    public function webLogout(): RedirectResponse
    {
        try {
            $this->webGateway()?->logout();
        } catch (WhatsAppException $e) {
            return back()->with("error", $e->getMessage());
        }

        return back()->with("success", __("app.wa_web_logged_out"));
    }

    private function webGateway(): ?WebSessionGateway
    {
        $gateway = app(WhatsAppChannels::class)->for("web");

        return config("whatsapp.driver") === "web" && $gateway instanceof WebSessionGateway ? $gateway : null;
    }

    public function update(Request $request): RedirectResponse
    {
        $events = array_keys(AutomaticNotifications::EVENTS);

        $rules = [];
        foreach ($events as $event) {
            $rules["events.$event.enabled"] = "nullable|boolean";
            $rules["events.$event.template_id"] = ["nullable", "integer", Rule::exists("message_templates", "id")];
            $rules["events.$event.days"] = "nullable|integer|min:1|max:365";
        }
        $validated = $request->validate($rules);

        foreach ($events as $event) {
            $input = $validated["events"][$event] ?? [];
            $enabled = (bool) ($input["enabled"] ?? false);

            if ($enabled && empty($input["template_id"])) {
                return back()->withInput()->withErrors(["events.$event.template_id" => __("app.wa_event_needs_template")]);
            }

            SiteSetting::set("notify_{$event}_enabled", $enabled ? "1" : "0");
            SiteSetting::set("notify_{$event}_template_id", $input["template_id"] ?? null);

            if (in_array($event, AutomaticNotifications::SCHEDULED, true) && isset($input["days"])) {
                SiteSetting::set("notify_{$event}_days", (int) $input["days"]);
            }
        }

        return redirect()->route("admin.notifications.index")->with("success", __("app.wa_settings_saved"));
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        MessageTemplate::create($this->templateData($request) + ["created_by" => $request->user()->id]);

        return redirect()->route("admin.notifications.index")->with("success", __("app.wa_template_saved"));
    }

    public function updateTemplate(Request $request, MessageTemplate $template): RedirectResponse
    {
        $template->update($this->templateData($request, $template));

        return redirect()->route("admin.notifications.index")->with("success", __("app.wa_template_saved"));
    }

    private function templateData(Request $request, ?MessageTemplate $template = null): array
    {
        $data = $request->validate([
            "name" => ["required", "string", "max:100", Rule::unique("message_templates", "name")->ignore($template?->id)],
            "body" => "required|string|max:1024",
            "wa_template_name" => ["nullable", "string", "max:120", "regex:/^[a-z0-9_]+$/"],
            "wa_language" => ["nullable", "string", "max:10", "regex:/^[a-z]{2}(_[A-Z]{2})?$/"],
            "wa_params" => ["nullable", "string", "max:255"],
            "is_active" => "nullable|boolean",
        ]);

        // "customer, order" -> ["customer", "order"]: the values for {{1}}, {{2}}… in Meta.
        $params = collect(explode(",", (string) ($data["wa_params"] ?? "")))
            ->map(fn ($p) => trim($p, " {}"))->filter()->values();

        $unknown = $params->diff(MessageTemplate::PLACEHOLDERS);
        if ($unknown->isNotEmpty()) {
            throw ValidationException::withMessages([
                "wa_params" => __("app.wa_unknown_params", ["params" => $unknown->join(", ")]),
            ]);
        }

        return [
            "name" => $data["name"],
            "body" => $data["body"],
            "wa_template_name" => $data["wa_template_name"] ?: null,
            "wa_language" => $data["wa_language"] ?: null,
            "wa_params" => $params->all() ?: null,
            "is_active" => $request->boolean("is_active"),
        ];
    }
}
