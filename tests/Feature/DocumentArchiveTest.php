<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Document::DISK);
    }

    private function upload(User $user, array $files, array $data = [])
    {
        return $this->actingAs($user)->post("/document-archive", ["files" => $files] + $data);
    }

    public function test_admin_owner_and_accountant_open_the_archive_others_do_not(): void
    {
        foreach ([User::factory()->create(), User::factory()->owner()->create(), User::factory()->accountant()->create()] as $user) {
            $this->actingAs($user)->get("/document-archive")
                ->assertOk()
                ->assertSee(__("app.doc_archive"))
                ->assertSee(__("app.doc_empty_archive"));
        }

        foreach ([User::factory()->cashier()->create(), User::factory()->courier()->create()] as $user) {
            $this->actingAs($user)->get("/document-archive")->assertForbidden();
            $this->upload($user, [UploadedFile::fake()->create("x.pdf", 10, "application/pdf")])->assertForbidden();
        }
    }

    public function test_office_and_pdf_files_are_uploaded_to_the_private_disk(): void
    {
        $owner = User::factory()->owner()->create();
        $folder = DocumentFolder::create(["name" => "Contratos"]);
        $category = DocumentCategory::create(["name" => "Legal", "color" => "danger"]);

        $this->upload($owner, [
            UploadedFile::fake()->create("contrato.pdf", 120, "application/pdf"),
            UploadedFile::fake()->create("orcamento.xlsx", 30, "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"),
            UploadedFile::fake()->create("relatorio.docx", 40, "application/vnd.openxmlformats-officedocument.wordprocessingml.document"),
            UploadedFile::fake()->create("apresentacao.pptx", 50, "application/vnd.openxmlformats-officedocument.presentationml.presentation"),
        ], [
            "folder_id" => $folder->id,
            "category_id" => $category->id,
            "tags" => "contrato, 2026 , Contrato",
            "expires_at" => today()->addDays(10)->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(4, Document::count());
        $doc = Document::where("extension", "pdf")->first();
        $this->assertSame("contrato", $doc->title);
        $this->assertSame(["contrato", "2026"], $doc->tags);
        $this->assertSame($folder->id, $doc->folder_id);
        $this->assertSame($owner->id, $doc->uploaded_by);
        Storage::disk(Document::DISK)->assertExists($doc->path);
        $this->assertSame(["excel", "pdf", "powerpoint", "word"], Document::all()->map->type()->sort()->values()->all());

        $page = $this->actingAs($owner)->get("/document-archive")->assertOk();
        $page->assertSee("contrato")->assertSee("Contratos")->assertSee("Legal");
    }

    public function test_other_file_types_are_rejected(): void
    {
        $owner = User::factory()->owner()->create();

        $this->upload($owner, [UploadedFile::fake()->create("virus.exe", 10)])->assertSessionHasErrors("files.0");
        $this->upload($owner, [UploadedFile::fake()->image("foto.png")])->assertSessionHasErrors("files.0");
        $this->upload($owner, [])->assertSessionHasErrors("files");

        $this->assertSame(0, Document::count());
    }

    public function test_single_file_uses_the_given_title(): void
    {
        $owner = User::factory()->owner()->create();

        $this->upload($owner, [UploadedFile::fake()->create("scan001.pdf", 10, "application/pdf")], ["title" => "Alvará 2026", "reference" => "ALV-01"])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas("documents", ["title" => "Alvará 2026", "reference" => "ALV-01", "original_name" => "scan001.pdf"]);
    }

    public function test_download_preview_and_delete_remove_the_file(): void
    {
        $owner = User::factory()->owner()->create();
        $this->upload($owner, [UploadedFile::fake()->create("balanco.pdf", 10, "application/pdf")]);
        $doc = Document::firstOrFail();

        $this->actingAs($owner)->get("/document-archive/{$doc->id}/download")
            ->assertOk()->assertDownload("balanco.pdf");
        $this->actingAs($owner)->get("/document-archive/{$doc->id}/preview")
            ->assertOk()->assertHeader("Content-Type", "application/pdf");

        $this->actingAs($owner)->delete("/document-archive/{$doc->id}")->assertRedirect();
        $this->assertSame(0, Document::count());
        Storage::disk(Document::DISK)->assertMissing($doc->path);
    }

    public function test_accountant_changes_only_own_documents(): void
    {
        $owner = User::factory()->owner()->create();
        $accountant = User::factory()->accountant()->create();
        $this->upload($owner, [UploadedFile::fake()->create("do-dono.pdf", 10, "application/pdf")]);
        $this->upload($accountant, [UploadedFile::fake()->create("do-contabilista.pdf", 10, "application/pdf")]);
        $ownerDoc = Document::where("title", "do-dono")->firstOrFail();
        $mine = Document::where("title", "do-contabilista")->firstOrFail();

        $this->actingAs($accountant)->get("/document-archive/{$ownerDoc->id}/download")->assertOk();
        $this->actingAs($accountant)->put("/document-archive/{$ownerDoc->id}", ["title" => "Mudado"])->assertForbidden();
        $this->actingAs($accountant)->delete("/document-archive/{$ownerDoc->id}")->assertForbidden();

        $this->actingAs($accountant)->put("/document-archive/{$mine->id}", ["title" => "Extracto", "tags" => "banco"])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame("Extracto", $mine->fresh()->title);
        $this->assertSame(["banco"], $mine->fresh()->tags);

        // Owner manages every document of the store.
        $this->actingAs($owner)->delete("/document-archive/{$mine->id}")->assertRedirect();
        $this->assertNull(Document::find($mine->id));
    }

    public function test_replacing_the_file_keeps_the_record_and_removes_the_old_file(): void
    {
        $owner = User::factory()->owner()->create();
        $this->upload($owner, [UploadedFile::fake()->create("v1.docx", 10)]);
        $doc = Document::firstOrFail();
        $oldPath = $doc->path;

        $this->actingAs($owner)->put("/document-archive/{$doc->id}", [
            "title" => $doc->title,
            "file" => UploadedFile::fake()->create("v2.pdf", 20, "application/pdf"),
        ])->assertSessionHasNoErrors();

        $doc->refresh();
        $this->assertSame("pdf", $doc->extension);
        $this->assertSame("v2.pdf", $doc->original_name);
        Storage::disk(Document::DISK)->assertMissing($oldPath);
        Storage::disk(Document::DISK)->assertExists($doc->path);
    }

    public function test_folders_and_categories_are_managed_and_deleting_keeps_documents(): void
    {
        $accountant = User::factory()->accountant()->create();

        $this->actingAs($accountant)->post("/document-archive/folders", ["name" => "Fiscal", "color" => "success"])->assertRedirect();
        $this->actingAs($accountant)->post("/document-archive/folders", ["name" => "Fiscal"])->assertSessionHasErrors("name", null, "folder");
        $this->actingAs($accountant)->post("/document-archive/categories", ["name" => "Impostos"])->assertRedirect();
        $folder = DocumentFolder::firstOrFail();
        $category = DocumentCategory::firstOrFail();
        $this->assertSame("primary", $category->color);

        $this->actingAs($accountant)->put("/document-archive/folders/{$folder->id}", ["name" => "Fiscal 2026", "color" => "info"])->assertRedirect();
        $this->assertSame("Fiscal 2026", $folder->fresh()->name);

        $this->upload($accountant, [UploadedFile::fake()->create("iva.pdf", 10, "application/pdf")], ["folder_id" => $folder->id, "category_id" => $category->id]);
        $doc = Document::firstOrFail();

        $this->actingAs($accountant)->delete("/document-archive/folders/{$folder->id}")->assertRedirect();
        $this->actingAs($accountant)->delete("/document-archive/categories/{$category->id}")->assertRedirect();

        $doc->refresh();
        $this->assertNull($doc->folder_id);
        $this->assertNull($doc->category_id);
    }

    public function test_filters_favorites_and_expiring(): void
    {
        $owner = User::factory()->owner()->create();
        $this->upload($owner, [UploadedFile::fake()->create("licenca.pdf", 10, "application/pdf")], ["expires_at" => today()->addDays(5)->toDateString(), "tags" => "licença"]);
        $this->upload($owner, [UploadedFile::fake()->create("vendas.xlsx", 10)], ["expires_at" => today()->addDays(90)->toDateString()]);
        $licence = Document::where("title", "licenca")->firstOrFail();

        $this->actingAs($owner)->get("/document-archive?view=expiring&layout=list")->assertSee("licenca")->assertDontSee("vendas.xlsx");
        $this->actingAs($owner)->get("/document-archive?type=excel&layout=list")->assertSee("vendas")->assertDontSee("licenca.pdf");
        $this->actingAs($owner)->get("/document-archive?q=LICEN&layout=list")->assertSee("licenca.pdf")->assertDontSee("vendas.xlsx");
        $this->actingAs($owner)->get("/document-archive?layout=list&tag=" . urlencode("licença"))->assertSee("licenca.pdf")->assertDontSee("vendas.xlsx");

        $this->actingAs($owner)->postJson("/document-archive/{$licence->id}/favorite")->assertJson(["favorite" => true]);
        $this->actingAs($owner)->get("/document-archive?view=favorites&layout=list")->assertSee("licenca.pdf")->assertDontSee("vendas.xlsx");
        // Favourites are personal.
        $admin = User::factory()->create();
        $this->actingAs($admin)->get("/document-archive?view=favorites&layout=list")->assertDontSee("licenca.pdf");
        $this->actingAs($owner)->postJson("/document-archive/{$licence->id}/favorite")->assertJson(["favorite" => false]);
    }

    public function test_malformed_filters_do_not_crash(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->get("/document-archive?from=lixo&to=2026-99-99&sort=x&type=exe&view=y&folder=abc")->assertOk();
        $this->actingAs($owner)->get("/document-archive?from[]=1&q[]=2&tag[]=3")->assertOk();
    }
}
