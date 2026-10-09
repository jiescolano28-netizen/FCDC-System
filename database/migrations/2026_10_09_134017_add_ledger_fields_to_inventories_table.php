<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->string('code')->nullable()->after('id');
            $table->text('description')->nullable();
            $table->string('status', 16)->default('active');
        });

        \Illuminate\Support\Facades\DB::table('inventories')->orderBy('id')->chunkById(500, function ($items) {
            foreach ($items as $item) {
                \Illuminate\Support\Facades\DB::table('inventories')
                    ->where('id', $item->id)
                    ->update(['code' => 'INV-'.str_pad((string) $item->id, 6, '0', STR_PAD_LEFT)]);

                if ((float) $item->qty !== 0.0) {
                    \Illuminate\Support\Facades\DB::table('stock_movements')->insert([
                        'inventory_id' => $item->id,
                        'type' => 'opening_balance',
                        'quantity' => $item->qty,
                        'reason_category' => 'opening_balance',
                        'notes' => 'Opening balance migrated from the inventory quantity.',
                        'effective_date' => substr($item->created_at ?? now()->toDateTimeString(), 0, 10),
                        'posted_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        Schema::table('inventories', function (Blueprint $table) {
            $table->string('code')->nullable(false)->change();
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'description', 'status']);
        });
    }
};
