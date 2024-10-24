<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Seat;
use App\Models\Theater;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoomController extends Controller
{


    // Get all rooms
    public function index()
    {
        $rooms = Room::all();

        if ($rooms->isEmpty()) {
            return response()->json(['message' => 'Không có dữ liệu rạp phim!'], 404);
        }

        return response()->json([
            'message' => 'Xuất dữ liệu Room thành công',
            'data' => $rooms,
        ], 200);
    }


    // hàm đến from add thêm mới đổ rạp phim để thêm khi thêm mới

    public function addRoom()
    {
        $theaters = Theater::all();

        if ($theaters->isEmpty()) {
            return response()->json(['message' => 'Không có dữ liệu rạp phim!'], 404);
        }

        return response()->json($theaters);

    }

    // Store new room
    public function store(Request $request)
    {
        $validated = $request->validate([
            'ten_phong_chieu' => 'required|string|max:250',
            'tong_ghe_phong' => 'required|integer',
            'rapphim_id' => 'required|exists:theaters,id',
        ]);

        $room = Room::create($validated);

        return response()->json([
            'message' => 'Thêm mới phòng chiếu phim thành công',
            'data' => $room,
        ], 201);
    }

    // Show room by id
    public function show(string $id)
    {
        $room = Room::find($id);

        if (!$room) {
            return response()->json(['message' => 'Không có dữ liệu Room theo id này'], 404);
        }

        return response()->json([
            'message' => 'Lấy thông tin Room theo ID thành công',
            'data' => $room,
        ], 200);
    }


    // đưa đến trang edit với thông tin edit đó và Theater để thay đổi rạp nếu muốn
    public function editRoom(string $id)
    {
        // show room theo id
        $roomID = Room::find($id);

        if (!$roomID) {
            return response()->json(['message' => 'Không có dữ liệu Room theo id này'], 404);
        }

        $theaters = Theater::all();
        if (!$theaters) {
            return response()->json(['message' => 'Không có dữ liệu Rạp nào'], 404);
        }

        return response()->json([
            'message' => 'Lấy thông tin Room theo ID thành công',
            'data' => [
                'room' => $roomID, // phong theo id
                'theaters' => $theaters,  // all rap phim

            ],
        ], 200);
    }


    public function update(Request $request, string $id)
    {
        // cap nhat room theo id 
        $room = Room::find($id);

        if (!$room) {
            return response()->json(['message' => 'Không có dữ liệu Room theo id này'], 404);
        }

        $validated = $request->validate([
            'ten_phong_chieu' => 'required|string|max:250',
            'tong_ghe_phong' => 'required|integer',
            'rapphim_id' => 'required|exists:theaters,id',
        ]);

        $room->update($validated);

        return response()->json([
            'message' => 'Cập nhật dữ liệu Room thành công',
            'data' => $room,
        ], 200);
    }


    public function delete(string $id)
    {
        $room = Room::find($id);

        if (!$room) {
            return response()->json(['message' => 'Không có dữ liệu Room theo id này'], 404);
        }

        $room->delete();

        return response()->json(['message' => 'Xóa Room theo id thành công'], 200);
    }

    
    // show all ghế theo phòng đó để xem all ghế và 1 số chức năng phụ
    public function allSeatRoom(string $id)
    {

        $roomID = Room::find($id);
       
        if (!$roomID) {
            return response()->json([
                'message' => 'Phòng không tồn tại',
            ], 404);
        }
        // show all ghế theo phòng đó theo id
        $allSeatRoom = DB::table('seats')->where('room_id', $roomID->id)->get();


        return response()->json([
            'message' => 'đổ toàn bộ ghế theo id room ok',
            'data' =>  $allSeatRoom
        ], 200);
    }


    // chức năng bảo trì tắt ghế ko cho thuê nếu gặp sự cố 
    public function baoTriSeat(string $id){
        // 0 la co the thue
        // 1 la da bi thue het thoi gian chieu phim set thanh 0 
        // 2 la cap nhat dang lỗi hoặc đang bảo trì ko cho thuê 
        
        $seatID = Seat::find($id);
        if (!$seatID) {
            return response()->json([
                'message' => 'Ghế không tồn tại',
            ], 404);
        }

        // cập nhật trạng thái là 2 bảo trị lỗi
        $seatID->update(['trang_thai' => 2]);
        

        return response()->json([
            'message' => 'Tắt ghế để bảo trì ghế theo id này ok',
            'data' => $seatID
        ], 200);
    }


    // tắt bảo trì ghế update lại trạng thái thành 0 có thể thuê
    // chức năng bảo trì tắt ghế ko cho thuê nếu gặp sự cố 
    public function tatbaoTriSeat(string $id){
        // 0 la co the thue
        // 1 la da bi thue het thoi gian chieu phim set thanh 0 
        // 2 la cap nhat dang lỗi hoặc đang bảo trì ko cho thuê 
        
        $seatID = Seat::find($id);
        if (!$seatID) {
            return response()->json([
                'message' => 'Ghế không tồn tại',
            ], 404);
        }

        // cập nhật trạng thái là 2 bảo trị lỗi
        $seatID->update(['trang_thai' => 0]);
        

        return response()->json([
            'message' => 'Bỏ bảo trì ghế ok có thể booking',
            'data' => $seatID
        ], 200);
    }


}
