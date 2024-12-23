<?php

namespace App\Http\Controllers\Api;

use App\Events\SeatSelectedEvent;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Food;
use App\Models\Payment;
use App\Models\Seat;
use App\Models\SeatShowtimeStatu;
use App\Models\Showtime;
use App\Models\Voucher;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

use function PHPUnit\Framework\isEmpty;

class BookingController extends Controller
{

    public function index()
    {

        $booking = Booking::all();

        if ($booking->isEmpty()) {
            return response()->json([
                'message' => 'Không có đơn booking nào'
            ], 404);
        }

        return response()->json([
            'message' => 'All Booking',
            'data' => $booking
        ], 200);
    }


    // hàm  truy vấn ghế đã lấy để thêm tên ghế ngồi vào cột ghe_ngoi
    public function getNameSeat(array $selectedSeats)
    {
        $seatNames = [];
        foreach ($selectedSeats as $seatId) {
            $seat = Seat::find($seatId);
            if ($seat) {
                $seatNames[] = $seat->so_ghe_ngoi;
            }
        }
        return $seatNames;
    }

    // hàm tính tổng tiền với giá phim , đồ ăn , số lượng ghế , giá ghế
    public function tongTien($showtime, array $selectedSeats, $foodPrice = 0)
    {
        $gia_ve_phim = $showtime->movie->gia_ve;
        $tong_gia_ve_phim = $gia_ve_phim * count($selectedSeats);

        $tong_tien_ghe = 0;
        foreach ($selectedSeats as $seatId) {
            $seat = Seat::find($seatId);
            if ($seat && isset($seat->gia_ghe)) {
                $tong_tien_ghe += $seat->gia_ghe;
            }
        }

        return $tong_gia_ve_phim + $tong_tien_ghe + $foodPrice;
    }

    // hàm sử dụng voucher nếu có sử dụng tính tiền khi sử dụng voucher
    public function tinhTienVoucher($ma_giam_gia, $tong_tien)
    {
        // Truy vấn mã giảm giá
        $voucher = Voucher::where('ma_giam_gia', $ma_giam_gia)
            ->where('ngay_het_han', '>=', Carbon::now())
            ->where('trang_thai', 0)  // Trạng thái mã giảm giá còn hiệu lực
            ->whereColumn('so_luong_da_su_dung', '<', 'so_luong')
            ->first();

        // Kiểm tra nếu không tồn tại mã giảm giá
        if (!$voucher) {
            return [
                'error' => 'Mã giảm giá không hợp lệ hoặc đã hết hạn.',
                'tong_tien_sau_giam' => $tong_tien,
            ];
        }

        // Kiểm tra nếu mã giảm giá đã hết số lượng
        if ($voucher->so_luong_da_su_dung >= $voucher->so_luong) {
            return [
                'error' => 'Mã giảm giá đã hết.',
                'tong_tien_sau_giam' => $tong_tien,
            ];
        }

        // Tính mức giảm giá theo % (nếu có)
        $muc_giam_gia = $voucher->muc_giam_gia;
        $giam_gia = $tong_tien * ($muc_giam_gia / 100);

        // Tổng tiền sau khi giảm
        $tong_tien_sau_giam = max($tong_tien - $giam_gia, 0); // Đảm bảo tổng tiền không âm

        // Cập nhật số lượng đã sử dụng
        $voucher->increment('so_luong_da_su_dung');

        return [
            'tong_tien_sau_giam' => $tong_tien_sau_giam,
        ];
    }


    // hàm realtime chọn ghế chặn realtime và bỏ chặn khi ấn lại ghế
    public function selectSeat(Request $request)
    {

        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Chưa đăng nhập, vui lòng đăng nhập'], 401);
        }

        $seatId = $request->input('ghengoi_id');
        $showtimeId = $request->input('thongtinchieu_id');

        // Kiểm tra nếu ghế đã bị chọn trước đó 
        $existingSeat = SeatShowtimeStatu::where('ghengoi_id', $seatId)
            ->where('thongtinchieu_id', $showtimeId)
            ->first();

        // check xem ghế đã được booking hay chưa 1 là đã lưu booking rồi
        if ($existingSeat && $existingSeat->trang_thai == 1) {
            return response()->json([
                'error' => 'Ghế đã được booking vé phim của khác hàng khác !',
                'data' => $seatId,
            ], 409);
        }

        // Nếu ghế đã chọn và người dùng muốn bỏ chọn cập nhật thành 0
        if ($existingSeat && $existingSeat->trang_thai == 3) {

            $existingSeat->update(['trang_thai' => 0, 'user_id' => null]); // bỏ chọn 

            //  sự kiện bỏ chọn ghế
            event(new SeatSelectedEvent($seatId, $showtimeId));

            return response()->json([
                'message' => 'Ghế đã được bỏ chọn thành công',
                'data' => $seatId,
            ]);
        }

        // Nếu ghế chưa được chọn hoặc đang ở trạng thái "Trống" (0)
        if ($existingSeat && $existingSeat->trang_thai !== 3) {

            SeatShowtimeStatu::updateOrInsert(
                ['ghengoi_id' => $seatId, 'thongtinchieu_id' => $showtimeId],
                ['trang_thai' => 3, 'user_id' => $user->id], // 3 đang chọn
            );

            // sự kiện chọn ghế
            event(new SeatSelectedEvent($seatId, $showtimeId));

            return response()->json([
                'message' => 'Ghế đã được chọn thành công',
                'data' => $seatId,
                'user_id' => $user->id
            ]);
        }
    }


    // Hàm xử lý đặt vé với đồ ăn và tính tiền
    public function Booking(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Chưa đăng nhập, vui lòng đăng nhập'], 401);
        }

        $request->validate([
            'thongtinchieu_id' => 'required|exists:showtimes,id',
            'ghe_ngoi' => 'required|array|min:1',
            'ghe_ngoi.*' => 'required|exists:seats,id',
            'doan' => 'nullable|array',
            'doan.*.doan_id' => 'nullable|exists:foods,id',
            'doan.*.so_luong_do_an' => 'nullable|numeric|min:1',
            'ma_giam_gia' => 'nullable|string|max:255',
            'ghi_chu' => 'nullable|string|max:255',
        ]);

        $showtime = Showtime::with('movie')->find($request->thongtinchieu_id);

        $selectedSeats = $request->ghe_ngoi;


        // check khi lưu booking theo thongtinhchieu_id và ghngoi
        // check trang_thai = 1 đặt đặt , 3 đang có người chọn ko cho lưu booking vói id ghế 
        $seatShowTimeStatus = SeatShowtimeStatu::where('thongtinchieu_id' , $request->thongtinchieu_id)
        ->whereIn('ghengoi_id' , $request->ghe_ngoi)->get();

        // lặp kiểm tra
        // duyệt qua mảng ghế và kiểm tra trạng thái
        $invalidSeats = [];
        foreach($seatShowTimeStatus as $seatStatus){
            if($seatStatus->trang_thai == 1){
                $invalidSeats[]  = $seatStatus->ghengoi_id;
            }
        }

        if (!empty($invalidSeats)) {
            return response()->json([

                // có thế chuyển hướng về trang front mong muốn 

                'message' => 'Một số ghế đã chọn không thể đặt vì không còn trống nữa !',
                'invalid_seats' => $invalidSeats // các ghế đã có người đặt or chọn rồi
            ], 400);
        } 



        $seatNames = $this->getNameSeat($selectedSeats);

        $doAnDetails = [];
        $tongTienDoAn = 0;
        if (!empty($request->doan)) {
            foreach ($request->doan as $doan) {
                $food = Food::find($doan['doan_id']);
                if ($food) {

                    $doAnDetails[] = ['ten_do_an' => $food->ten_do_an, 'so_luong_do_an' => $doan['so_luong_do_an']];
                    $tongTienDoAn += $food->gia * $doan['so_luong_do_an'];
                }
            }
        }


        $doAnString = $this->formatDoAnString($doAnDetails);


        // Tính tổng tiền
        $tongTien = $this->tongTien($showtime, $selectedSeats, $tongTienDoAn);

        // Kiểm tra và áp dụng mã giảm giá nếu có
        if ($request->ma_giam_gia) {
            $result = $this->tinhTienVoucher($request->ma_giam_gia, $tongTien);
            if (isset($result['error'])) {
                return response()->json($result, 400);
            }
            $tongTien = $result['tong_tien_sau_giam'];
        }

        $barcode = 'VE-' . substr(strval(rand(10000, 999999)), 0, 6);
        $id = rand(10000, 9999999);

        // Tạo Booking
        // 0 Chưa thanh toán , 2 là Đã thanh toán , 1 Đã huy đơn , 3 Lỗi đơn hàng ,
        $booking = Booking::create([
            'id' => $id,
            'user_id' => $user->id,
            'thongtinchieu_id' => $request->thongtinchieu_id,
            'so_luong' => count($selectedSeats),
            'ngay_mua' => Carbon::now(),
            'trang_thai' => 0,  // Chưa thanh toán 
            'ghe_ngoi' => implode(', ', $seatNames),
            'do_an' => $doAnString,
            'ma_giam_gia' => $request->ma_giam_gia,
            'ghi_chu' => $request->ghi_chu,
            'tong_tien' => $tongTien,
            'tong_tien_thanh_toan' => $tongTien,
            'barcode' => $barcode, // ma barcode
        ]);

         // Cập nhật trạng thái ghế trong seat_showtime_status
         foreach ($selectedSeats as $seatId) {
            DB::table('seat_showtime_status')->where('thongtinchieu_id', $request->thongtinchieu_id)->where('ghengoi_id' , $seatId)->update(['trang_thai' => 1 , 'user_id'=>$user->id]);
        }
       

        return response()->json([
            'message' => 'Tạo Booking thành công, vui lòng thanh toán.',
            'data' => $booking,
            //'doan_details' =>  $doAnDetails // chỉ để xem dữ liệu thôi
        ], 201);
    }

    // Hàm format tên món ăn và số lượng món ăn thành chuỗi
    public function formatDoAnString($doanDetails)
    {
        $doAnList = [];
        foreach ($doanDetails as $doan) {
            if (isset($doan['ten_do_an']) && isset($doan['so_luong_do_an'])) {
                $doAnList[] = $doan['ten_do_an'] . ' (x' . $doan['so_luong_do_an'] . ')';
            }
        }
        return implode(', ', $doAnList);
    }


    // xu ly sau
    public function update(Request $request, string $id)
    {

        // dd($request->all());
       
    }

    // 
    public function delete(string $id)
    {
        // delete theo id
        $dataID = Booking::find($id);

        if (!$dataID) {
            return response()->json([
                'message' => 'Không có booking theo id này'
            ], 404);
        }

        $dataID->delete();

        return response()->json([
            'message' => 'Xóa thành công booking theo id'
        ], 200);
    }


}
