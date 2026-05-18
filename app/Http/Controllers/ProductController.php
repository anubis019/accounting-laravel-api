<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::where('user_id', auth()->id());

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->low_stock) {
            $query->whereRaw('quantity <= min_stock_level');
        }

        $products = $query->orderBy('name')->paginate($request->per_page ?? 20);

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'quantity' => 'required|integer|min:0',
            'min_stock_level' => 'required|integer|min:0',
            'unit_price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'sku' => 'nullable|string|unique:products'
        ]);

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'user_id' => auth()->id(),
            'name' => $request->name,
            'description' => $request->description,
            'sku' => $request->sku,
            'quantity' => $request->quantity,
            'min_stock_level' => $request->min_stock_level,
            'unit_price' => $request->unit_price,
            'cost_price' => $request->cost_price
        ]);

        AuditLog::log(auth()->id(), 'create', 'products', $product->id, null, $product->toArray());

        return response()->json($product, 201);
    }

    public function show($id)
    {
        $product = Product::where('user_id', auth()->id())->findOrFail($id);
        return response()->json($product);
    }

    public function update(Request $request, $id)
    {
        $product = Product::where('user_id', auth()->id())->findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'quantity' => 'sometimes|integer|min:0',
            'min_stock_level' => 'sometimes|integer|min:0',
            'unit_price' => 'sometimes|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0'
        ]);

        $oldValues = $product->toArray();
        $product->update($request->all());
        
        AuditLog::log(auth()->id(), 'update', 'products', $product->id, $oldValues, $product->toArray());

        return response()->json($product);
    }

    public function destroy($id)
    {
        $product = Product::where('user_id', auth()->id())->findOrFail($id);
        $product->delete();
        
        AuditLog::log(auth()->id(), 'delete', 'products', $product->id, $product->toArray(), null);

        return response()->json(['message' => 'Product deleted']);
    }

    public function sell(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'customer_name' => 'nullable|string',
            'payment_method' => 'nullable|string'
        ]);

        $product = Product::where('user_id', auth()->id())->findOrFail($id);

        if ($product->quantity < $request->quantity) {
            return response()->json([
                'error' => 'Insufficient stock',
                'available' => $product->quantity,
                'requested' => $request->quantity
            ], 400);
        }

        DB::beginTransaction();

        try {
            $totalAmount = $product->unit_price * $request->quantity;
            
            // Create transaction
            $transaction = Transaction::create([
                'id' => (string) Str::uuid(),
                'user_id' => auth()->id(),
                'type' => 'income',
                'amount' => $totalAmount,
                'description' => "Sale of {$request->quantity} x {$product->name}",
                'transaction_date' => now(),
                'customer_name' => $request->customer_name,
                'product_id' => $product->id
            ]);

            // Update stock
            $product->decreaseStock($request->quantity);

            DB::commit();

            AuditLog::log(auth()->id(), 'sell', 'products', $product->id, null, [
                'quantity_sold' => $request->quantity,
                'total_amount' => $totalAmount,
                'transaction_id' => $transaction->id
            ]);

            return response()->json([
                'message' => 'Sale recorded successfully',
                'transaction' => $transaction,
                'product' => $product
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Sale failed: ' . $e->getMessage()], 500);
        }
    }

    public function restock(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'cost_price' => 'nullable|numeric|min:0',
            'supplier_id' => 'nullable|exists:suppliers,id'
        ]);

        $product = Product::where('user_id', auth()->id())->findOrFail($id);

        $product->increaseStock($request->quantity);

        if ($request->cost_price) {
            // Update average cost price
            $totalCost = ($product->quantity * $product->cost_price) + ($request->quantity * $request->cost_price);
            $product->cost_price = $totalCost / ($product->quantity + $request->quantity);
            $product->save();
        }

        AuditLog::log(auth()->id(), 'restock', 'products', $product->id, null, [
            'quantity_added' => $request->quantity,
            'new_quantity' => $product->quantity
        ]);

        return response()->json([
            'message' => 'Stock updated successfully',
            'product' => $product
        ]);
    }

    public function lowStock()
    {
        $products = Product::where('user_id', auth()->id())
            ->whereRaw('quantity <= min_stock_level')
            ->get();

        return response()->json([
            'count' => $products->count(),
            'products' => $products
        ]);
    }
}