<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::all();
        
        return response()->json([
            'success' => true,
            'message' => 'Daftar produk',
            'data' => $products
        ], 200);
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:products',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'unit' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $product = new Product();
        $product->code = $request->code;
        $product->name = $request->name;
        $product->description = $request->description;
        $product->price = $request->price;
        $product->stock = $request->stock;
        $product->unit = $request->unit;
        
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $image->storeAs('public/products', $imageName);
            $product->image = 'products/' . $imageName;
        }
        
        $product->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan',
            'data' => $product
        ], 201);
    }
    
    public function show($id)
    {
        $product = Product::find($id);
        
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Detail produk',
            'data' => $product
        ], 200);
    }
    
    public function update(Request $request, $id)
    {
        $product = Product::find($id);
        
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }
        
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:products,code,' . $id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'unit' => 'required|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $product->code = $request->code;
        $product->name = $request->name;
        $product->description = $request->description;
        $product->price = $request->price;
        $product->stock = $request->stock;
        $product->unit = $request->unit;
        
        if ($request->hasFile('image')) {
            // Hapus gambar lama jika ada
            if ($product->image) {
                Storage::delete('public/' . $product->image);
            }
            
            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $image->storeAs('public/products', $imageName);
            $product->image = 'products/' . $imageName;
        }
        
        $product->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil diperbarui',
            'data' => $product
        ], 200);
    }
    
    public function destroy($id)
    {
        $product = Product::find($id);
        
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }
        
        // Hapus gambar jika ada
        if ($product->image) {
            Storage::delete('public/' . $product->image);
        }
        
        $product->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil dihapus'
        ], 200);
    }
}
