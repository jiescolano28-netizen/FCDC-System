<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | GET /inventory
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        $items = Inventory::orderBy('id', 'desc')->get();

        $items->transform(function ($item) {

            if ($item->image) {
                // Convert stored path into a public URL
                $item->image = asset($item->image);
            }

            return $item;
        });

        return response()->json($items);
    }


    /*
    |--------------------------------------------------------------------------
    | POST /inventory
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'qty' => 'required|numeric|min:0',
            'unit' => 'required|string|max:50',
            'unit_cost' => 'required|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'reorder_level' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $uploadDirectory = public_path('uploads/inventory');
            if (! is_dir($uploadDirectory)) {
                mkdir($uploadDirectory, 0755, true);
            }
            $imageName = time().'_'.uniqid().'.'.$request->file('image')->getClientOriginalExtension();
            $request->file('image')->move($uploadDirectory, $imageName);
            $imagePath = 'uploads/inventory/'.$imageName;
        }

        $item = DB::transaction(fn () => Inventory::create([
            ...$validated,
            'selling_price' => $validated['selling_price'] ?? null,
            'reorder_level' => $validated['reorder_level'] ?? 10,
            'image' => $imagePath,
        ]));

        if ($item->image) {
            $item->image = asset($item->image);
        }

        return response()->json($item, 201);
    }


    /*
    |--------------------------------------------------------------------------
    | PUT /inventory/{id}
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, $id)
    {
        $inventory = Inventory::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'unit_cost' => 'required|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'reorder_level' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $inventory->fill([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'unit' => $validated['unit'],
            'unit_cost' => $validated['unit_cost'],
            'selling_price' => $validated['selling_price'] ?? null,
            'reorder_level' => $validated['reorder_level'] ?? 10,
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? $inventory->status,
        ]);

        if ($request->hasFile('image')) {
            $uploadDirectory = public_path('uploads/inventory');
            if (! is_dir($uploadDirectory)) {
                mkdir($uploadDirectory, 0755, true);
            }

            if ($inventory->image && file_exists(public_path($inventory->image))) {
                unlink(public_path($inventory->image));
            }

            $imageName = time().'_'.uniqid().'.'.$request->file('image')->getClientOriginalExtension();
            $request->file('image')->move($uploadDirectory, $imageName);
            $inventory->image = 'uploads/inventory/'.$imageName;
        }

        $inventory->save();
        $inventory->image = $inventory->image ? asset($inventory->image) : null;

        return response()->json([
            'message' => 'Inventory item updated successfully.',
            'inventory' => $inventory,
        ]);
    }

    public function destroy($id)
    {
        $inventory = Inventory::findOrFail($id);
        $inventory->status = 'inactive';
        $inventory->save();

        return response()->json([
            'message' => 'Inventory item deactivated successfully.',
        ]);
    }
}