<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    // Lấy danh sách tất cả các Voucher
    public function index()
    {
        $voucherAll = Voucher::all();

        if ($voucherAll->isEmpty()) {
            return response()->json([
                'message' => 'Không có dữ liệu Voucher!',
            ], 200);
        }

        return response()->json([
            'message' => 'Xuất dữ liệu Voucher thành công',
            'data' => $voucherAll,
        ], 200);
    }

    // Thêm mới Voucher
    public function store(Request $request)
    {

        $validated = $request->validate([
            'ma_giam_gia' => 'required|string|max:255',
            'muc_giam_gia' => 'required|numeric',
            'mota' => 'required|string|max:255',
            'ngay_het_han' => 'required|date',
            'so_luong' => 'required|integer|min:1',
        ]);

        $vouhchers = Voucher::create($validated);

        return response()->json([
            'message' => 'Thêm mới voucher thành công',
            'data' => $vouhchers
        ], 201);
    }

    // Lấy thông tin Voucher theo ID
    public function show(string $id)
    {
        $voucher = Voucher::find($id);

        if (!$voucher) {
            return response()->json([
                'message' => 'Không có dữ liệu Voucher theo ID này',
            ], 404);
        }

        return response()->json([
            'message' => 'Lấy thông tin Voucher thành công',
            'data' => $voucher,
        ], 200);
    }

    // Lấy dữ liệu để chỉnh sửa
    public function edit(string $id)
    {
        $voucher = Voucher::find($id);

        if (!$voucher) {
            return response()->json([
                'message' => 'Không có dữ liệu Voucher theo ID này',
            ], 404);
        }

        return response()->json([
            'message' => 'Lấy thông tin Voucher để chỉnh sửa thành công',
            'data' => $voucher,
        ], 200);
    }

    // Cập nhật Voucher
    public function update(Request $request, string $id)
    {
        $voucher = Voucher::find($id);

        if (!$voucher) {
            return response()->json([
                'message' => 'Không có dữ liệu Voucher theo ID này',
            ], 404);
        }

        $validated = $request->validate([
            'ma_giam_gia' => 'required|string|max:255',
            'muc_giam_gia' => 'required|numeric',
            'mota' => 'required|string|max:255',
            'ngay_het_han' => 'required|date',
            'so_luong' => 'required|integer|min:1',
        ]);

        $voucher->update($validated);

        return response()->json([
            'message' => 'Cập nhật dữ liệu Voucher thành công',
            'data' => $voucher,
        ], 200);
    }

    // Xóa Voucher theo ID
    public function delete(string $id)
    {
        $voucher = Voucher::find($id);

        if (!$voucher) {
            return response()->json([
                'message' => 'Không có dữ liệu Voucher theo ID này',
            ], 404);
        }

        $voucher->delete();

        return response()->json([
            'message' => 'Xóa Voucher thành công',
        ], 200);
    }
}
