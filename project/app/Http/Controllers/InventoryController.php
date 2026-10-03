<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use Illuminate\Http\Request;

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
        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'qty' => 'required|numeric|min:0',
            'unit' => 'required|string|max:50',
            'unit_cost' => 'required|numeric|min:0',
            'reorder_level' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Upload image directly to public/uploads/inventory
        |--------------------------------------------------------------------------
        */

        $imagePath = null;

        if ($request->hasFile('image')) {

            // Make sure the directory exists
            $uploadDirectory = public_path('uploads/inventory');

            if (!is_dir($uploadDirectory)) {
                mkdir($uploadDirectory, 0755, true);
            }

            // Generate a unique filename
            $imageName = time() . '_' . uniqid() . '.' .
                $request->file('image')->getClientOriginalExtension();

            // Move image into public folder
            $request->file('image')->move(
                $uploadDirectory,
                $imageName
            );

            // Store relative path in database
            $imagePath = 'uploads/inventory/' . $imageName;
        }


        /*
        |--------------------------------------------------------------------------
        | Create inventory record
        |--------------------------------------------------------------------------
        */

        $item = Inventory::create([
            'name' => $request->name,
            'category' => $request->category,
            'qty' => $request->qty,
            'unit' => $request->unit,
            'unit_cost' => $request->unit_cost,
            'reorder_level' => $request->reorder_level ?? 10,
            'image' => $imagePath,
        ]);


        /*
        |--------------------------------------------------------------------------
        | Return public image URL
        |--------------------------------------------------------------------------
        */

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
            'qty' => 'required|numeric|min:0',
            'unit' => 'required|string|max:50',
            'unit_cost' => 'required|numeric|min:0',
            'reorder_level' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);


        /*
        |--------------------------------------------------------------------------
        | Update normal inventory information
        |--------------------------------------------------------------------------
        */

        $inventory->name = $validated['name'];
        $inventory->category = $validated['category'];
        $inventory->qty = $validated['qty'];
        $inventory->unit = $validated['unit'];
        $inventory->unit_cost = $validated['unit_cost'];
        $inventory->reorder_level = $validated['reorder_level'] ?? 10;


        /*
        |--------------------------------------------------------------------------
        | Replace image if a new image was selected
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('image')) {

            $uploadDirectory = public_path('uploads/inventory');

            // Create directory if it doesn't exist
            if (!is_dir($uploadDirectory)) {
                mkdir($uploadDirectory, 0755, true);
            }


            /*
            |--------------------------------------------------------------------------
            | Delete old image
            |--------------------------------------------------------------------------
            */

            if ($inventory->image) {

                $oldImagePath = public_path($inventory->image);

                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Generate new filename
            |--------------------------------------------------------------------------
            */

            $imageName = time() . '_' . uniqid() . '.' .
                $request->file('image')->getClientOriginalExtension();


            /*
            |--------------------------------------------------------------------------
            | Move new image
            |--------------------------------------------------------------------------
            */

            $request->file('image')->move(
                $uploadDirectory,
                $imageName
            );


            /*
            |--------------------------------------------------------------------------
            | Save new image path
            |--------------------------------------------------------------------------
            */

            $inventory->image = 'uploads/inventory/' . $imageName;
        }


        /*
        |--------------------------------------------------------------------------
        | Save all changes
        |--------------------------------------------------------------------------
        */

        $inventory->save();


        /*
        |--------------------------------------------------------------------------
        | Return updated inventory
        |--------------------------------------------------------------------------
        */

        $inventory->image = $inventory->image
            ? asset($inventory->image)
            : null;

        return response()->json([
            'message' => 'Inventory item updated successfully.',
            'inventory' => $inventory,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE /inventory/{id}
    |--------------------------------------------------------------------------
    */
    public function destroy($id)
    {
        $inventory = Inventory::findOrFail($id);


        /*
        |--------------------------------------------------------------------------
        | Delete image from public/uploads/inventory
        |--------------------------------------------------------------------------
        */

        if ($inventory->image) {

            $imagePath = public_path($inventory->image);

            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Delete inventory database record
        |--------------------------------------------------------------------------
        */

        $inventory->delete();

        return response()->json([
            'message' => 'Inventory item deleted successfully.'
        ]);
    }
}