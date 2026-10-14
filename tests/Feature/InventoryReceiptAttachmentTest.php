<?php

use App\Livewire\Inventory\InventoryProfile;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\InventoryReceiptAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function receiptManager(): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'receipt.manager.'.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => Hash::make('secret-password'),
    ]), ['inventory.view', 'inventory.update']);
}

function receiptInventory(): Inventory
{
    return Inventory::create([
        'name' => 'Reclaimed timber',
        'category' => 'Lumber',
        'qty' => 0,
        'unit' => 'piece',
        'unit_cost' => 0,
        'reorder_level' => 3,
    ]);
}

test('a user can upload multiple receipt files of any type', function () {
    Storage::fake('local');
    $manager = receiptManager();
    $this->actingAs($manager);
    $inventory = receiptInventory();

    $response = $this->post(route('inventory.receipts.store', $inventory), [
        'receiptFiles' => [
            UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('photo.jpg', 200, 'image/jpeg'),
            UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ],
    ]);

    $response->assertCreated();
    expect(InventoryReceiptAttachment::where('inventory_id', $inventory->id)->count())->toBe(3);

    foreach (InventoryReceiptAttachment::all() as $attachment) {
        Storage::disk('local')->assertExists($attachment->path);
        expect($attachment->uploaded_by)->toBe($manager->id);
    }
});

test('a receipt file larger than 10 MB is rejected without persisting anything', function () {
    Storage::fake('local');
    $this->actingAs(receiptManager());
    $inventory = receiptInventory();

    $this->post(route('inventory.receipts.store', $inventory), [
        'receiptFiles' => [UploadedFile::fake()->create('huge.bin', 10241)],
    ])->assertSessionHasErrors('receiptFiles.0');

    expect(InventoryReceiptAttachment::count())->toBe(0);
});

test('uploading a receipt requires the inventory.update permission', function () {
    Storage::fake('local');
    $viewer = grantEmployeeTestPermissions(Employee::create([
        'username' => 'receipt.viewer.'.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => Hash::make('secret-password'),
    ]), ['inventory.view']);
    $this->actingAs($viewer);
    $inventory = receiptInventory();

    $this->post(route('inventory.receipts.store', $inventory), [
        'receiptFiles' => [UploadedFile::fake()->create('receipt.pdf', 10)],
    ])->assertForbidden();

    expect(InventoryReceiptAttachment::count())->toBe(0);
});

test('receipt attachments can be listed as json', function () {
    Storage::fake('local');
    $this->actingAs(receiptManager());
    $inventory = receiptInventory();

    $this->post(route('inventory.receipts.store', $inventory), [
        'receiptFiles' => [UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf')],
    ])->assertCreated();

    $this->get(route('inventory.receipts.index', $inventory))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['original_name' => 'receipt.pdf']);
});

test('a receipt file can be downloaded through the authorized route', function () {
    Storage::fake('local');
    $this->actingAs(receiptManager());
    $inventory = receiptInventory();

    $this->post(route('inventory.receipts.store', $inventory), [
        'receiptFiles' => [UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf')],
    ])->assertCreated();

    $attachment = InventoryReceiptAttachment::firstOrFail();

    $this->get(route('inventory.receipts.download', $attachment))->assertOk();
});

test('a receipt image can be previewed inline through the authorized route', function () {
    Storage::fake('local');
    $this->actingAs(receiptManager());
    $inventory = receiptInventory();

    $this->post(route('inventory.receipts.store', $inventory), [
        'receiptFiles' => [UploadedFile::fake()->create('photo.jpg', 20, 'image/jpeg')],
    ])->assertCreated();

    $attachment = InventoryReceiptAttachment::firstOrFail();

    expect($attachment->isImage())->toBeTrue();
    $this->get(route('inventory.receipts.preview', $attachment))->assertOk();
});

test('deleting a receipt removes both its row and its stored file', function () {
    Storage::fake('local');
    $this->actingAs(receiptManager());
    $inventory = receiptInventory();

    $this->post(route('inventory.receipts.store', $inventory), [
        'receiptFiles' => [UploadedFile::fake()->create('receipt.pdf', 10)],
    ])->assertCreated();

    $attachment = InventoryReceiptAttachment::firstOrFail();
    Storage::disk('local')->assertExists($attachment->path);

    $this->delete(route('inventory.receipts.destroy', $attachment))->assertOk();

    expect(InventoryReceiptAttachment::count())->toBe(0);
    Storage::disk('local')->assertMissing($attachment->path);
});

test('the inventory profile shows a thumbnail and opens a preview modal for an image', function () {
    Storage::fake('local');
    $this->actingAs(receiptManager());
    $inventory = receiptInventory();

    $this->post(route('inventory.receipts.store', $inventory), [
        'receiptFiles' => [UploadedFile::fake()->create('photo.jpg', 20, 'image/jpeg')],
    ])->assertCreated();

    $attachment = InventoryReceiptAttachment::firstOrFail();

    Livewire::test(InventoryProfile::class, ['id' => $inventory->id])
        ->assertSeeHtml('receipt-thumb')
        ->assertDontSeeHtml('receipt-preview-image')
        ->call('preview', $attachment->id)
        ->assertSeeHtml('receipt-preview-image')
        ->call('closePreview')
        ->assertDontSeeHtml('receipt-preview-image');
});

test('uploading an image through the livewire profile records its image mime type', function () {
    Storage::fake('local');
    $this->actingAs(receiptManager());
    $inventory = receiptInventory();

    // The real UI path: Livewire's TemporaryUploadedFile does not carry a client
    // mime type, so the stored type must come from content detection.
    Livewire::test(InventoryProfile::class, ['id' => $inventory->id])
        ->set('receiptFiles', [UploadedFile::fake()->image('photo.jpg', 40, 40)])
        ->call('uploadReceipts')
        ->assertHasNoErrors()
        ->assertSeeHtml('receipt-thumb');

    $attachment = InventoryReceiptAttachment::firstOrFail();

    expect($attachment->mime_type)->toStartWith('image/');
    expect($attachment->isImage())->toBeTrue();
});

test('a legacy attachment recorded as octet-stream still previews when its name is an image', function () {
    Storage::fake('local');
    $this->actingAs(receiptManager());
    $inventory = receiptInventory();

    $path = UploadedFile::fake()->image('photo.jpg', 40, 40)->store('inventory/receipts', 'local');

    $attachment = InventoryReceiptAttachment::create([
        'inventory_id' => $inventory->id,
        'original_name' => 'photo.jpg',
        'path' => $path,
        'disk' => 'local',
        'mime_type' => 'application/octet-stream',
        'size_bytes' => 1024,
    ]);

    expect($attachment->isImage())->toBeTrue();

    $this->get(route('inventory.receipts.preview', $attachment))
        ->assertOk()
        ->assertHeader('content-type', 'image/jpeg');
});
