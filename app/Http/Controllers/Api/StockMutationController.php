<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockCard;
use App\Models\StockMutation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StockMutationController extends Controller
{
    public function index(Request $request)
    {
        $query = StockMutation::with(['product', 'user']);
        
        // Filter berdasarkan produk jika diberikan
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }
        
        // Filter berdasarkan tipe mutasi jika diberikan
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }
        
        // Filter berdasarkan rentang tanggal jika diberikan
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }
        
        $stockMutations = $query->orderBy('date', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'message' => 'Daftar mutasi stok',
            'data' => $stockMutations
        ], 200);
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'type' => 'required|in:in,out',
            'quantity' => 'required|integer|min:1',
            'date' => 'required|date',
            'description' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Ambil produk
        $product = Product::find($request->product_id);
        
        // Hitung stok baru
        $beforeStock = $product->stock;
        $quantity = $request->quantity;
        $afterStock = ($request->type == 'in') ? $beforeStock + $quantity : $beforeStock - $quantity;
        
        // Validasi stok tidak boleh kurang dari 0 untuk tipe keluar
        if ($request->type == 'out' && $afterStock < 0) {
            return response()->json([
                'success' => false,
                'message' => 'Stok tidak mencukupi untuk mutasi keluar'
            ], 422);
        }
        
        DB::beginTransaction();
        try {
            // Buat mutasi stok
            $stockMutation = StockMutation::create([
                'product_id' => $request->product_id,
                'type' => $request->type,
                'quantity' => $quantity,
                'before_stock' => $beforeStock,
                'after_stock' => $afterStock,
                'date' => $request->date,
                'description' => $request->description,
                'user_id' => auth()->id()
            ]);
            
            // Update stok produk
            $product->stock = $afterStock;
            $product->save();
            
            // Update atau buat kartu stok
            $date = Carbon::parse($request->date)->toDateString();
            $stockCard = StockCard::firstOrNew([
                'product_id' => $request->product_id,
                'date' => $date
            ]);
            
            if (!$stockCard->exists) {
                // Kartu stok baru
                $stockCard->initial_stock = $beforeStock;
                $stockCard->in_stock = ($request->type == 'in') ? $quantity : 0;
                $stockCard->out_stock = ($request->type == 'out') ? $quantity : 0;
                $stockCard->final_stock = $afterStock;
            } else {
                // Update kartu stok yang sudah ada
                if ($request->type == 'in') {
                    $stockCard->in_stock += $quantity;
                } else {
                    $stockCard->out_stock += $quantity;
                }
                $stockCard->final_stock = $stockCard->initial_stock + $stockCard->in_stock - $stockCard->out_stock;
            }
            
            $stockCard->notes = $stockCard->notes ? $stockCard->notes . "\n" . $request->description : $request->description;
            $stockCard->save();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Mutasi stok berhasil ditambahkan',
                'data' => $stockMutation
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function show($id)
    {
        $stockMutation = StockMutation::with(['product', 'user'])->find($id);
        
        if (!$stockMutation) {
            return response()->json([
                'success' => false,
                'message' => 'Mutasi stok tidak ditemukan'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Detail mutasi stok',
            'data' => $stockMutation
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
        
        $stockMutations = StockMutation::where('product_id', $productId)
                                    ->with('user')
                                    ->orderBy('date', 'desc')
                                    ->get();
        
        return response()->json([
            'success' => true,
            'message' => 'Daftar mutasi stok untuk produk',
            'data' => [
                'product' => $product,
                'stock_mutations' => $stockMutations
            ]
        ], 200);
    }
}
