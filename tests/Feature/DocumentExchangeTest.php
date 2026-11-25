<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\Store;
use App\Models\User;
use App\View\Composers\AdminNavigationComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentExchangeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Document::DISK);
        $this->admin = User::factory()->create(["name" => "Ana Admin"]);
        $this->owner = User::factory()->owner()->create(["name" => "Óscar Dono"]);
        $this->accountant = User::factory()->accountant()->create(["name" => "Carla Contabilista"]);
    }

    private function pdf(string $name = "extrato.pdf"): UploadedFile
    {
        return UploadedFile::fake()->create($name, 50, "application/pdf");
    }

    private function send(User $from, array $recipients, array $data = [])
    {
        return $this->actingAs($from)->post(route("documents-archive.send"), [
            "files" => [$this->pdf()],
            "recipients" => array_map(fn (User $u) => $u->id, $recipients),
        ] + $data);
    }

    public function test_the_three_roles_send_and_receive_documents_from_each_other(): void
    {
        $pairs = [
            [$this->accountant, $this->owner], [$this->owner, $this->accountant],
            [$this->admin, $this->accountant], [$this->accountant, $this->admin],
            [$this->owner, $this->admin], [$this->admin, $this->owner],
        ];

        foreach ($pairs as [$from, $to]) {
            $this->send($from, [$to], ["title" => "De {$from->name} para {$to->name}", "message" => "Para conferir"])
                ->assertRedirect()->assertSessionHasNoErrors();

            $this->actingAs($to)->get(route("documents-archive.index", ["view" => "received"]))
                ->assertOk()->assertSee("De {$from->name} para {$to->name}")->assertSee("Para conferir")->assertSee($from->name);
            $this->actingAs($from)->get(route("documents-archive.index", ["view" => "sent"]))
                ->assertOk()->assertSee("De {$from->name} para {$to->name}");
        }

        $this->assertSame(6, DocumentShare::count());
        $this->assertSame(6, Document::where("is_private", true)->count());
    }

    public function test_a_private_document_is_seen_only_by_the_sender_and_the_recipients(): void
    {
        $this->send($this->accountant, [$this->owner], ["title" => "Salários de outubro"])->assertSessionHasNoErrors();
        $document = Document::firstOrFail();
        $this->assertTrue($document->is_private);

        $this->actingAs($this->owner)->get(route("documents-archive.download", $document))->assertOk();
        $this->actingAs($this->accountant)->get(route("documents-archive.download", $document))->assertOk();

        // The admin was not a recipient: not in the archive, no access, not for the AI either.
        $this->actingAs($this->admin)->get(route("documents-archive.index"))->assertOk()->assertDontSee("Salários de outubro");
        $this->actingAs($this->admin)->get(route("documents-archive.download", $document))->assertForbidden();
        $this->actingAs($this->admin)->get(route("documents-archive.edit", $document))->assertForbidden();
        $this->assertSame(0, Document::visibleTo($this->admin)->count());

        // Only the uploader changes or deletes it, even the owner who received it may not.
        $this->actingAs($this->owner)->delete(route("documents-archive.destroy", $document))->assertForbidden();
        $this->actingAs($this->accountant)->put(route("documents-archive.update", $document), ["title" => "Salários 10/2026", "is_private" => 1])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame("Salários 10/2026", $document->fresh()->title);
    }

    public function test_kept_in_the_store_archive_everyone_of_the_three_roles_sees_it(): void
    {
        $this->send($this->owner, [$this->accountant], ["title" => "Contrato de arrendamento", "share_with_store" => 1])->assertSessionHasNoErrors();

        $this->assertFalse(Document::firstOrFail()->is_private);
        $this->actingAs($this->admin)->get(route("documents-archive.index"))->assertSee("Contrato de arrendamento");
    }

    public function test_a_document_of_the_archive_is_forwarded_and_read_receipt_is_kept(): void
    {
        $this->actingAs($this->owner)->post(route("documents-archive.store"), ["files" => [$this->pdf("balancete.pdf")]])->assertSessionHasNoErrors();
        $document = Document::firstOrFail();

        $this->actingAs($this->owner)->post(route("documents-archive.forward", $document), [
            "recipients" => [$this->accountant->id, $this->admin->id],
            "message" => "Ver antes de sexta",
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, DocumentShare::unread()->count());
        $this->actingAs($this->accountant)->get(route("dashboard"))->assertOk();

        // Opening it marks it read for that recipient only.
        $this->actingAs($this->accountant)->get(route("documents-archive.download", $document))->assertOk();
        $this->assertNotNull(DocumentShare::where("recipient_id", $this->accountant->id)->first()->read_at);
        $this->assertNull(DocumentShare::where("recipient_id", $this->admin->id)->first()->read_at);

        $share = DocumentShare::where("recipient_id", $this->admin->id)->first();
        $this->actingAs($this->accountant)->post(route("documents-archive.shares.read", $share))->assertForbidden();
        $this->actingAs($this->admin)->post(route("documents-archive.shares.read", $share))->assertRedirect();
        $this->assertNotNull($share->fresh()->read_at);
    }

    public function test_recipient_removes_it_and_sender_takes_it_back(): void
    {
        $this->send($this->accountant, [$this->owner, $this->admin])->assertSessionHasNoErrors();
        $document = Document::firstOrFail();
        $toOwner = DocumentShare::where("recipient_id", $this->owner->id)->firstOrFail();
        $toAdmin = DocumentShare::where("recipient_id", $this->admin->id)->firstOrFail();

        $this->actingAs($this->admin)->delete(route("documents-archive.shares.destroy", $toOwner))->assertForbidden();

        $this->actingAs($this->owner)->delete(route("documents-archive.shares.destroy", $toOwner))->assertRedirect();
        $this->actingAs($this->owner)->get(route("documents-archive.download", $document))->assertForbidden();

        $this->actingAs($this->accountant)->delete(route("documents-archive.shares.destroy", $toAdmin))->assertRedirect();
        $this->assertSame(0, DocumentShare::count());
        // The sender keeps the document.
        $this->actingAs($this->accountant)->get(route("documents-archive.download", $document))->assertOk();
    }

    public function test_only_admin_owner_and_accountant_of_the_store_can_be_recipients(): void
    {
        $cashier = User::factory()->cashier()->create();
        $inactive = User::factory()->accountant()->create(["is_active" => false]);
        $other = Store::create(["name" => "Loja 2", "code" => "LJ09", "is_active" => true]);
        $otherOwner = User::factory()->owner()->create(["store_id" => $other->id]);

        foreach ([$cashier, $inactive, $otherOwner, $this->accountant] as $user) {
            $this->send($this->accountant, [$user])->assertSessionHasErrorsIn("exchange", "recipients.0");
        }
        $this->assertSame(0, Document::count());

        $this->actingAs($cashier)->post(route("documents-archive.send"), ["files" => [$this->pdf()], "recipients" => [$this->owner->id]])->assertForbidden();
        $this->send($this->accountant, [])->assertSessionHasErrorsIn("exchange", "recipients");
    }

    public function test_unread_documents_show_a_badge_in_the_menu(): void
    {
        $this->send($this->accountant, [$this->owner])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->get(route("documents-archive.index"))
            ->assertOk()->assertSee(__("app.doc_unread_received"));
        // The badges are counted once per request (singleton): a new request, a new count.
        $this->app->forgetInstance(AdminNavigationComposer::class);
        $this->actingAs($this->accountant)->get(route("documents-archive.index"))
            ->assertOk()->assertDontSee(__("app.doc_unread_received"));
    }
}
