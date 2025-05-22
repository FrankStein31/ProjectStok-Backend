<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockCard;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StockCardController extends Controller
{
    public function index(Request $request)
    {
        $query = StockCard::with('product');
        
        // Filter berdasarkan produk jika diberikan
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }
        
        // Filter berdasarkan rentang tanggal jika diberikan
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }
        
        $stockCards = $query->orderBy('date', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'message' => 'Daftar kartu stok',
            'data' => $stockCards
        ], 200);
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'initial_stock' => 'required|integer|min:0',
            'in_stock' => 'required|integer|min:0',
            'out_stock' => 'required|integer|min:0',
            'final_stock' => 'required|integer|min:0',
            'date' => 'required|date',
            'notes' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Cek apakah sudah ada kartu stok untuk produk pada tanggal yang sama
        $existingCard = StockCard::where('product_id', $request->product_id)
                               ->where('date', Carbon::parse($request->date)->toDateString())
                               ->first();
        
        if ($existingCard) {
            return response()->json([
                'success' => false,
                'message' => 'Kartu stok untuk produk ini pada tanggal yang sama sudah ada'
            ], 422);
        }
        
        $stockCard = StockCard::create($request->all());
        
        // Update stok di produk
        $product = Product::find($request->product_id);
        $product->stock = $request->final_stock;
        $product->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Kartu stok berhasil ditambahkan',
            'data' => $stockCard
        ], 201);
    }
    
    public function show($id)
    {
        $stockCard = StockCard::with('product')->find($id);
        
        if (!$stockCard) {
            return response()->json([
                'success' => false,
                'message' => 'Kartu stok tidak ditemukan'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Detail kartu stok',
            'data' => $stockCard
        ], 200);
    }
    
    public function update(Request $request, $id)
    {
        $stockCard = StockCard::find($id);
        
        if (!$stockCard) {
            return response()->json([
                'success' => false,
                'message' => 'Kartu stok tidak ditemukan'
            ], 404);
        }
        
        $validator = Validator::make($request->all(), [
            'initial_stock' => 'required|integer|min:0',
            'in_stock' => 'required|integer|min:0',
            'out_stock' => 'required|integer|min:0',
            'final_stock' => 'required|integer|min:0',
            'date' => 'required|date',
            'notes' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Cek apakah tanggal berubah dan apakah sudah ada kartu stok lain pada tanggal yang sama
        if ($request->date != $stockCard->date) {
            $existingCard = StockCard::where('product_id', $stockCard->product_id)
                                   ->where('date', Carbon::parse($request->date)->toDateString())
                                   ->where('id', '!=', $id)
                                   ->first();
            
            if ($existingCard) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kartu stok untuk produk ini pada tanggal yang sama sudah ada'
                ], 422);
            }
        }
        
        $stockCard->initial_stock = $request->initial_stock;
        $stockCard->in_stock = $request->in_stock;
        $stockCard->out_stock = $request->out_stock;
        $stockCard->final_stock = $request->final_stock;
        $stockCard->date = $request->date;
        $stockCard->notes = $request->notes;
        $stockCard->save();
        
        // Update stok di produk jika ini adalah kartu stok terbaru
        $latestCard = StockCard::where('product_id', $stockCard->product_id)
                             ->orderBy('date', 'desc')
                             ->first();
        
        if ($latestCard->id == $stockCard->id) {
            $product = Product::find($stockCard->product_id);
            $product->stock = $request->final_stock;
            $product->save();
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Kartu stok berhasil diperbarui',
            'data' => $stockCard
        ], 200);
    }
    
    public function destroy($id)
    {
        $stockCard = StockCard::find($id);
        
        if (!$stockCard) {
            return response()->json([
                'success' => false,
                'message' => 'Kartu stok tidak ditemukan'
            ], 404);
        }
        
        $productId = $stockCard->product_id;
        $stockCard->delete();
        
        // Update stok di produk berdasarkan kartu stok terbaru
        $latestCard = StockCard::where('product_id', $productId)
                             ->orderBy('date', 'desc')
                             ->first();
        
        if ($latestCard) {
            $product = Product::find($productId);
            $product->stock = $latestCard->final_stock;
            $product->save();
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Kartu stok berhasil dihapus'
        ], 200);
    }
    
    public function getByProduct($productId)
    {
        $product = Product::find($productId);
        
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }
        
        $stockCards = StockCard::where('product_id', $productId)
                             ->orderBy('date', 'desc')
                             ->get();
        
        return response()->json([
            'success' => true,
            'message' => 'Daftar kartu stok untuk produk',
            'data' => [
                'product' => $product,
                'stock_cards' => $stockCards
            ]
        ], 200);
    }
}
