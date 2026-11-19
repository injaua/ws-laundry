<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ChoosesSettingsLevel;
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
use App\Support\CurrentStore;
use App\Support\StoreSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * WhatsApp channels (incl. the QR connection), automatic notifications and message templates.
 *
 * The channels are the company's, or the store's own (admin, ?level=company|store); the
 * automatic notifications and the templates always belong to the store worked in.
 */
class NotificationSettingController extends Controller
{
    use ChoosesSettingsLevel;

    public function index(Request $request, AutomaticNotifications $notifications): View
    {
        $events = collect(AutomaticNotifications::EVENTS)->map(fn ($defaults, $event) => (object) [
            "key" => $event,
            "enabled" => $notifications->enabled($event),
            "template_id" => $notifications->chosenTemplate($event)?->id,
            "days" => in_array($event, AutomaticNotifications::SCHEDULED, true) ? $notifications->days($event) : null,
            "sent_30d" => WhatsAppMessage::where("notification_key", "like", "{$event}:%")->where("created_at", ">=", now()->subDays(30))->where("status", "!=", "failed")->count(),
            "failed_30d" => WhatsAppMessage::where("notification_key", "like", "{$event}:%")->where("created_at", ">=", now()->subDays(30))->where("status", "failed")->count(),
        ]);

        // The channels shown: those of the level being edited (company, or the store's own).
        $level = $this->settingsLevel($request, "whatsapp");
        $config = WhatsAppSettings::forLevel($level);
        $notificationsDriver = ($config["notifications_driver"] ?? null) ?: $config["driver"];
        $notificationChannel = in_array($notificationsDriver, WhatsAppChannels::NOTIFICATION_DRIVERS, true) ? $notificationsDriver : null;
        $stored = StoreSettings::values("whatsapp", $level);

        return view("admin.notifications.index", [
            "events" => $events,
            "templates" => MessageTemplate::orderBy("name")->get(),
            "currentStore" => CurrentStore::store(),
            "levelBar" => $this->levelBar("whatsapp", $level, "admin.notifications.index"),
            "levelParam" => $level === null ? "company" : "store",
            "conversationChannel" => $config["driver"],
            "drivers" => WhatsAppSettings::DRIVERS,
            "notificationsSetting" => (string) ($stored["whatsapp_notifications_driver"] ?? ($level === null ? (config("whatsapp.notifications_driver") ?? "") : "")),
            "notificationChannel" => $notificationChannel,
            "usesCloud" => in_array("cloud", [$config["driver"], $notificationChannel], true),
            // The fields show what is stored at this level; at company level the .env values in use
            // appear as placeholders, so saving the form never copies them into the database.
            "cloud" => [
                "phone_number_id" => $stored["whatsapp_cloud_phone_number_id"] ?? null,
                "verify_token" => $stored["whatsapp_cloud_verify_token"] ?? null,
                "phone_number_id_hint" => $level === null ? ($config["cloud"]["phone_number_id"] ?? null) : null,
                "verify_token_hint" => $level === null ? ($config["cloud"]["verify_token"] ?? null) : null,
                "has_access_token" => filled($config["cloud"]["access_token"] ?? null),
                "has_app_secret" => filled($config["cloud"]["app_secret"] ?? null),
            ],
            "configured" => filled($config["cloud"]["phone_number_id"] ?? null) && filled($config["cloud"]["access_token"] ?? null),
            "webTokenOk" => strlen((string) config("whatsapp.web.token")) >= 16,
            "webhookSigned" => filled($config["cloud"]["app_secret"] ?? null),
            "webhookUrl" => route("webhooks.whatsapp"),
            "recentFailures" => WhatsAppMessage::with("customer:id,name")->where("direction", "out")->where("status", "failed")
                ->latest("id")->limit(10)->get(),
        ]);
    }

    /**
     * Channel of the conversations (log / official API / a number linked by QR code) and of the
     * automatic notifications, with the official API number, for the company or for the store
     * worked in. Choosing the QR code also creates the secret shared with the service.
     */
    public function updateChannels(Request $request, WhatsAppWebService $service): RedirectResponse
    {
        $data = $request->validate([
            "level" => ["nullable", Rule::in(["company", "store"])],
            "driver" => ["required", Rule::in(WhatsAppSettings::DRIVERS)],
            // The unofficial QR connection never sends automatic notifications (the number would be blocked).
            "notifications_driver" => ["nullable", Rule::in(WhatsAppSettings::NOTIFICATION_DRIVERS)],
            "cloud" => ["nullable", "array"],
            "cloud.phone_number_id" => ["nullable", "string", "max:40", "regex:/^\d+$/"],
            "cloud.access_token" => ["nullable", "string", "max:1000"],
            "cloud.app_secret" => ["nullable", "string", "max:255"],
            "cloud.verify_token" => ["nullable", "string", "max:255"],
        ]);

        $level = $this->settingsLevel($request, "whatsapp", forSaving: true);
        // Whether this level's own number was linked by QR code (a store using the company's has none).
        $wasWeb = ($level === null || StoreSettings::owns("whatsapp", $level)) && WhatsAppSettings::forLevel($level)["driver"] === "web";

        WhatsAppSettings::save($data["driver"], (string) ($data["notifications_driver"] ?? ""), $level, $request->has("cloud") ? (array) ($data["cloud"] ?? []) : null);

        // QR code chosen: start the service right away (first run installs it); otherwise stop it
        // when nobody chats by QR code any more, or just disconnect this number.
        $message = __("app.wa_channels_saved");
        if ($data["driver"] === "web") {
            if ($service->enabled()) {
                $message .= " " . __("app.wa_web_autostart_" . $service->start());
            }
        } elseif ($wasWeb) {
            $this->leaveQr($level, $service);
        }

        return redirect()->route("admin.notifications.index", $this->levelQuery($level, "whatsapp"))->with("success", $message);
    }

    /** The store worked in goes back to the company WhatsApp configuration (its own number is disconnected). */
    public function releaseChannels(WhatsAppWebService $service): RedirectResponse
    {
        $storeId = CurrentStore::id();
        abort_unless($storeId && StoreSettings::owns("whatsapp", $storeId), 404);

        $wasWeb = WhatsAppSettings::forLevel($storeId)["driver"] === "web";
        StoreSettings::release("whatsapp", $storeId);
        if ($wasWeb) {
            $this->leaveQr($storeId, $service);
        }

        return redirect()->route("admin.notifications.index", ["level" => "store"])->with("success", __("app.store_settings_released"));
    }

    /** A level stopped chatting by QR code: disconnect its number, or stop the service if nobody uses it. */
    private function leaveQr(?int $level, WhatsAppWebService $service): void
    {
        if (!WhatsAppSettings::anyUsesWeb()) {
            if ($service->enabled()) {
                $service->stop();
            }

            return;
        }

        try {
            (new WebSessionGateway(["session" => WhatsAppSettings::session($level)] + (array) config("whatsapp.web")))->close();
        } catch (WhatsAppException) {
            // the service is down: nothing to disconnect
        }
    }

    /** "Start service" button, when the QR service is not running. */
    public function webStart(Request $request, WhatsAppWebService $service): RedirectResponse
    {
        abort_unless($this->webGateway($request), 404);
        $result = $service->start();

        return back()->with($result === "failed" ? "error" : "success", __("app.wa_web_autostart_" . $result));
    }

    /** State of the QR connection, polled by the settings page while the QR is shown. */
    public function webStatus(Request $request, WhatsAppWebService $service): JsonResponse
    {
        $status = $this->webGateway($request)?->status() ?? ["status" => "disabled", "qr" => null, "me" => null];

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
    public function webLogout(Request $request): RedirectResponse
    {
        try {
            $this->webGateway($request)?->logout();
        } catch (WhatsAppException $e) {
            return back()->with("error", $e->getMessage());
        }

        return back()->with("success", __("app.wa_web_logged_out"));
    }

    /** The QR connection of the level being edited (company or the store's own), when it chats by QR code. */
    private function webGateway(Request $request): ?WebSessionGateway
    {
        $config = WhatsAppSettings::forLevel($this->settingsLevel($request, "whatsapp"));
        $gateway = WhatsAppChannels::make("web", $config);

        return $config["driver"] === "web" && $gateway instanceof WebSessionGateway ? $gateway : null;
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
