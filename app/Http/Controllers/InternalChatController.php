<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Support\CurrentStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InternalChatController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $conversations = Conversation::forUser($user)
            ->with(["lastMessage.author", "participants"])
            ->get()
            ->sortByDesc(fn (Conversation $c) => $c->lastMessage?->created_at ?? $c->created_at)
            ->values();

        $selectedId = $request->integer("conversation") ?: $conversations->first()?->id;
        $conversation = $selectedId ? $conversations->firstWhere("id", $selectedId) : null;

        if ($conversation) {
            $conversation->load(["messages.author"]);
            $conversation->participants()->updateExistingPivot($user->id, ["last_read_at" => now()]);
        }

        $pickerUsers = User::colleagues()->where("is_active", true)->whereKeyNot($user->id)->orderBy("name")->get();

        return view("messages.index", compact("conversations", "conversation", "pickerUsers"));
    }

    public function storeConversation(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            "type" => ["required", Rule::in(Conversation::TYPES)],
            "user_ids" => ["required", "array", "min:1"],
            "user_ids.*" => ["integer", "distinct", Rule::exists("users", "id")->where("is_active", true)->where(fn ($q) => $q->where("store_id", CurrentStore::id())->orWhere("role", "admin"))],
            "title" => ["nullable", "string", "max:150", Rule::requiredIf(fn () => $request->input("type") === "meeting")],
        ]);

        $others = collect($data["user_ids"])->map(fn ($id) => (int) $id)->unique();

        if ($data["type"] === "direct") {
            if ($others->count() !== 1 || $others->contains($user->id)) {
                return back()->withErrors(["user_ids" => __("app.direct_needs_one_other")])->withInput();
            }

            $existing = Conversation::where("type", "direct")
                ->whereHas("participants", fn ($q) => $q->whereKey($user->id))
                ->whereHas("participants", fn ($q) => $q->whereKey($others->first()))
                ->first();

            if ($existing) {
                return redirect()->route("messages.index", ["conversation" => $existing->id]);
            }
        } else {
            $others = $others->reject(fn ($id) => $id === $user->id);

            if ($others->count() < 2) {
                return back()->withErrors(["user_ids" => __("app.meeting_needs_two")])->withInput();
            }
        }

        $conversation = Conversation::create([
            "type" => $data["type"],
            "title" => $data["type"] === "meeting" ? $data["title"] : null,
            "created_by" => $user->id,
        ]);

        $conversation->participants()->attach($others->push($user->id)->unique()->all());
        $conversation->participants()->updateExistingPivot($user->id, ["last_read_at" => now()]);

        return redirect()->route("messages.index", ["conversation" => $conversation->id])
            ->with("success", __("app.conversation_created"));
    }

    public function show(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->ensureParticipant($request, $conversation);

        return redirect()->route("messages.index", ["conversation" => $conversation->id]);
    }

    public function storeMessage(Request $request, Conversation $conversation): RedirectResponse
    {
        $user = $this->ensureParticipant($request, $conversation);

        $data = $request->validate([
            "body" => ["required", "string", "max:2000"],
        ]);

        ChatMessage::create([
            "conversation_id" => $conversation->id,
            "user_id" => $user->id,
            "body" => $data["body"],
        ]);

        $conversation->participants()->updateExistingPivot($user->id, ["last_read_at" => now()]);

        return redirect()->route("messages.index", ["conversation" => $conversation->id]);
    }

    public function poll(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $this->ensureParticipant($request, $conversation);

        $since = $request->integer("since");

        $messages = $conversation->messages()
            ->with("author:id,name")
            ->when($since, fn ($q) => $q->where("id", ">", $since))
            ->orderBy("id")
            ->get()
            ->map(fn (ChatMessage $m) => [
                "id" => $m->id,
                "body" => $m->body,
                "user" => $m->author?->name,
                "at" => $m->created_at->format(datetime_fmt()),
                "mine" => $m->user_id === $user->id,
            ]);

        $conversation->participants()->updateExistingPivot($user->id, ["last_read_at" => now()]);

        return response()->json(["messages" => $messages]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(["count" => $request->user()->unreadChatCount()]);
    }

    private function ensureParticipant(Request $request, Conversation $conversation): User
    {
        $user = $request->user();
        $conversation->loadMissing("participants:id");
        abort_unless($conversation->hasParticipant($user), 403);

        return $user;
    }
}
