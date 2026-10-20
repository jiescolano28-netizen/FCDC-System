<?php

use App\Livewire\Inventory\BatchCategoryManagement;
use App\Models\Employee;
use App\Models\InventoryBatchCategory;
use App\Models\InventoryBatchCategoryAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function batchCategoryAttachmentManager(array $permissions = ['inventory.view', 'inventory.update']): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'batch.attachment.manager.'.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => Hash::make('secret-password'),
    ]), $permissions);
}

function batchCategoryAttachmentCategory(Employee $creator): InventoryBatchCategory
{
    return InventoryBatchCategory::create([
        'name' => 'Attachment category '.uniqid(),
        'description' => null,
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ]);
}

test('a user can upload multiple attachment files against a batch category', function () {
    Storage::fake('local');
    $manager = batchCategoryAttachmentManager();
    $this->actingAs($manager);
    $category = batchCategoryAttachmentCategory($manager);

    $response = $this->post(route('inventory.batch-categories.attachments.store', $category), [
        'attachmentFiles' => [
            UploadedFile::fake()->create('spec.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('photo.jpg', 200, 'image/jpeg'),
            UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ],
    ]);

    $response->assertCreated();
    expect(InventoryBatchCategoryAttachment::where('inventory_batch_category_id', $category->id)->count())->toBe(3);

    foreach (InventoryBatchCategoryAttachment::all() as $attachment) {
        Storage::disk('local')->assertExists($attachment->path);
        expect($attachment->uploaded_by)->toBe($manager->id);
    }
});

test('an attachment file larger than 10 MB is rejected without persisting anything', function () {
    Storage::fake('local');
    $manager = batchCategoryAttachmentManager();
    $this->actingAs($manager);
    $category = batchCategoryAttachmentCategory($manager);

    $this->post(route('inventory.batch-categories.attachments.store', $category), [
        'attachmentFiles' => [UploadedFile::fake()->create('huge.bin', 10241)],
    ])->assertSessionHasErrors('attachmentFiles.0');

    expect(InventoryBatchCategoryAttachment::count())->toBe(0);
});

test('uploading a batch category attachment requires the inventory.update permission', function () {
    Storage::fake('local');
    $viewer = batchCategoryAttachmentManager(['inventory.view']);
    $this->actingAs($viewer);
    $category = batchCategoryAttachmentCategory($viewer);

    $this->post(route('inventory.batch-categories.attachments.store', $category), [
        'attachmentFiles' => [UploadedFile::fake()->create('spec.pdf', 10)],
    ])->assertForbidden();

    expect(InventoryBatchCategoryAttachment::count())->toBe(0);
});

test('batch category attachments can be listed as json', function () {
    Storage::fake('local');
    $manager = batchCategoryAttachmentManager();
    $this->actingAs($manager);
    $category = batchCategoryAttachmentCategory($manager);

    $this->post(route('inventory.batch-categories.attachments.store', $category), [
        'attachmentFiles' => [UploadedFile::fake()->create('spec.pdf', 10, 'application/pdf')],
    ])->assertCreated();

    $this->get(route('inventory.batch-categories.attachments.index', $category))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['original_name' => 'spec.pdf']);
});

test('a batch category attachment can be downloaded and an image previewed through authorized routes', function () {
    Storage::fake('local');
    $manager = batchCategoryAttachmentManager();
    $this->actingAs($manager);
    $category = batchCategoryAttachmentCategory($manager);

    $this->post(route('inventory.batch-categories.attachments.store', $category), [
        'attachmentFiles' => [UploadedFile::fake()->create('photo.jpg', 20, 'image/jpeg')],
    ])->assertCreated();

    $attachment = InventoryBatchCategoryAttachment::firstOrFail();

    expect($attachment->isImage())->toBeTrue();
    $this->get(route('inventory.batch-categories.attachments.download', $attachment))->assertOk();
    $this->get(route('inventory.batch-categories.attachments.preview', $attachment))->assertOk();
});

test('deleting a batch category attachment removes both its row and its stored file', function () {
    Storage::fake('local');
    $manager = batchCategoryAttachmentManager();
    $this->actingAs($manager);
    $category = batchCategoryAttachmentCategory($manager);

    $this->post(route('inventory.batch-categories.attachments.store', $category), [
        'attachmentFiles' => [UploadedFile::fake()->create('spec.pdf', 10)],
    ])->assertCreated();

    $attachment = InventoryBatchCategoryAttachment::firstOrFail();
    Storage::disk('local')->assertExists($attachment->path);

    $this->delete(route('inventory.batch-categories.attachments.destroy', $attachment))->assertOk();

    expect(InventoryBatchCategoryAttachment::count())->toBe(0);
    Storage::disk('local')->assertMissing($attachment->path);
});

test('the batch category page manages attachments through livewire', function () {
    Storage::fake('local');
    $manager = batchCategoryAttachmentManager();
    $this->actingAs($manager);
    $category = batchCategoryAttachmentCategory($manager);

    $this->get(route('inventory.batch-categories'))
        ->assertOk()
        ->assertSee('Attachments')
        ->assertSee('id="batch-category-attachments-modal"', false);

    Livewire::test(BatchCategoryManagement::class)
        ->call('manageAttachments', $category->id)
        ->assertDispatched('batch-category-attachments-modal-open')
        ->assertSet('attachmentsCategoryId', $category->id)
        ->assertSee('Batch category files')
        ->set('attachmentFiles', [UploadedFile::fake()->create('photo.jpg', 40, 'image/jpeg')])
        ->call('uploadAttachments')
        ->assertHasNoErrors()
        ->assertDispatched('batch-category-attachments-uploaded')
        ->assertSeeHtml('receipt-thumb');

    $attachment = InventoryBatchCategoryAttachment::firstOrFail();
    expect($attachment->inventory_batch_category_id)->toBe($category->id)
        ->and($attachment->isImage())->toBeTrue();

    Livewire::test(BatchCategoryManagement::class)
        ->call('manageAttachments', $category->id)
        ->call('previewAttachment', $attachment->id)
        ->assertSeeHtml('receipt-preview-image')
        ->call('closeAttachmentPreview')
        ->assertDontSeeHtml('receipt-preview-image')
        ->call('deleteAttachment', $attachment->id);

    expect(InventoryBatchCategoryAttachment::count())->toBe(0);
    Storage::disk('local')->assertMissing($attachment->path);
});

test('managing batch category attachments requires a batch category file and inventory permissions', function () {
    Storage::fake('local');
    $manager = batchCategoryAttachmentManager();
    $this->actingAs($manager);
    $category = batchCategoryAttachmentCategory($manager);

    Livewire::test(BatchCategoryManagement::class)
        ->call('manageAttachments', $category->id)
        ->set('attachmentFiles', [])
        ->call('uploadAttachments')
        ->assertHasErrors('attachmentFiles');

    $viewer = batchCategoryAttachmentManager(['inventory.view']);
    $this->actingAs($viewer);

    Livewire::test(BatchCategoryManagement::class)
        ->call('manageAttachments', $category->id)
        ->set('attachmentFiles', [UploadedFile::fake()->create('spec.pdf', 10)])
        ->call('uploadAttachments')
        ->assertForbidden();

    $withoutView = batchCategoryAttachmentManager([]);
    $this->actingAs($withoutView)
        ->get(route('inventory.batch-categories'))
        ->assertForbidden();
});
