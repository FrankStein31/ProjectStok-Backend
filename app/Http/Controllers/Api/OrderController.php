<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\StockMutation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['user', 'orderDetails.product']);
        
        // Filter berdasarkan pengguna jika bukan admin
        if (auth()->user()->role != 'admin') {
            $query->where('user_id', auth()->id());
        } else if ($request->has('user_id')) {
            // Admin bisa filter berdasarkan user_id
            $query->where('user_id', $request->user_id);
        }
        
        // Filter berdasarkan status jika diberikan
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter berdasarkan tanggal
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }
        
        $orders = $query->orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'message' => 'Daftar pesanan',
            'data' => $orders
        ], 200);
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'shipping_address' => 'required|string',
            'notes' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        
        DB::beginTransaction();
        try {
            // Buat nomor pesanan
            $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(Str::random(5));
            
            // Hitung total amount
            $totalAmount = 0;
            $items = [];
            
            // Validasi stok
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                if (!$product) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Produk tidak ditemukan'
                    ], 404);
                }
                
                if ($product->stock < $item['quantity']) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Stok produk ' . $product->name . ' tidak mencukupi'
                    ], 422);
                }
                
                $subtotal = $product->price * $item['quantity'];
                $totalAmount += $subtotal;
                
                $items[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                    'subtotal' => $subtotal
                ];
            }
            
            // Buat pesanan
            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => auth()->id(),
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'shipping_address' => $request->shipping_address,
                'notes' => $request->notes
            ]);
            
            // Buat detail pesanan dan kurangi stok
            foreach ($items as $item) {
                $product = $item['product'];
                $quantity = $item['quantity'];
                
                // Simpan detail pesanan
                OrderDetail::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'price' => $item['price'],
                    'subtotal' => $item['subtotal']
                ]);
                
                // Kurangi stok
                $beforeStock = $product->stock;
                $afterStock = $beforeStock - $quantity;
                $product->stock = $afterStock;
                $product->save();
                
                // Catat mutasi stok
                StockMutation::create([
                    'product_id' => $product->id,
                    'type' => 'out',
                    'quantity' => $quantity,
                    'before_stock' => $beforeStock,
                    'after_stock' => $afterStock,
                    'date' => Carbon::now()->toDateString(),
                    'description' => 'Pesanan #' . $orderNumber,
                    'user_id' => auth()->id()
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil dibuat',
                'data' => Order::with(['orderDetails.product'])->find($order->id)
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
        // Tentukan apakah pengguna dapat melihat pesanan ini
        $query = Order::with(['orderDetails.product', 'user']);
        
        if (auth()->user()->role != 'admin') {
            $query->where('user_id', auth()->id());
        }
        
        $order = $query->find($id);
        
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Detail pesanan',
            'data' => $order
        ], 200);
    }
    
    public function updateStatus(Request $request, $id)
    {
        // Hanya admin yang dapat mengubah status
        if (auth()->user()->role != 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Tidak diizinkan'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,processing,completed,cancelled'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $order = Order::find($id);
        
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan tidak ditemukan'
            ], 404);
        }
        
        // Jika status berubah menjadi 'cancelled', kembalikan stok
        if ($request->status == 'cancelled' && $order->status != 'cancelled') {
            DB::beginTransaction();
            try {
                foreach ($order->orderDetails as $detail) {
                    $product = Product::find($detail->product_id);
                    $beforeStock = $product->stock;
                    $afterStock = $beforeStock + $detail->quantity;
                    
                    // Update stok produk
                    $product->stock = $afterStock;
                    $product->save();
                    
                    // Catat mutasi stok (in)
                    StockMutation::create([
                        'product_id' => $product->id,
                        'type' => 'in',
                        'quantity' => $detail->quantity,
                        'before_stock' => $beforeStock,
                        'after_stock' => $afterStock,
                        'date' => Carbon::now()->toDateString(),
                        'description' => 'Pembatalan Pesanan #' . $order->order_number,
                        'user_id' => auth()->id()
                    ]);
                }
                
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Terjadi kesalahan: ' . $e->getMessage()
                ], 500);
            }
        }
        
        $order->status = $request->status;
        $order->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Status pesanan berhasil diperbarui',
            'data' => $order
        ], 200);
    }
    
    public function getMyOrders()
    {
        $orders = Order::with(['orderDetails.product'])
                     ->where('user_id', auth()->id())
                     ->orderBy('created_at', 'desc')
                     ->get();
        
        return response()->json([
            'success' => true,
            'message' => 'Daftar pesanan saya',
            'data' => $orders
        ], 200);
    }
}
