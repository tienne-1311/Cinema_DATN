<?php

namespace App\Http\Controllers\Api;

use Auth;
use Carbon\Carbon;
use App\Models\Booking;
use App\Models\Payment;
use Endroid\QrCode\QrCode;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\BookingDetail;
use App\Models\RegisterMember;
use App\Models\MemberShips;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Endroid\QrCode\Writer\PngWriter;

class PaymentController extends Controller
{


    // đưa đến from chọn phương thức thanh toán
    public function createPayment($bookingId, $method)
    {

        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'message' => 'Chưa đăng nhập phải đăng nhập'
            ], 401);
        }

        $booking = Booking::find($bookingId);
        if (!$booking) {
            return response()->json(['message' => 'No booking id'], 404);
        }

        //enum('Đang chờ xử lý','Đã hoàn thành','Không thành công','Đã hoàn lại','Đã hủy')
        if ($booking->trang_thai !== 0) {
            return response()->json(['error' => 'Booking đã được thanh toán'], 400);
        }

        $money = $booking->tong_tien_thanh_toan;

        $payment = new Payment();
        $payment->booking_id = $booking->id;
        $payment->tong_tien = $money;
        //$payment->tien_te = 'VND'; 
        $payment->phuong_thuc_thanh_toan = $method;
        $payment->trang_thai = 'Đang chờ xử lý';
        $payment->ngay_thanh_toan = Carbon::now();
        $payment->save();


        // 'credit_card','paypal','cash','bank_transfer','vietqr','vnpay','viettel_money','payoo','mastercard','visa','ncb','jcb'

        switch ($method) {
            case 'ncb':
                return $this->paymentNCB($booking, $money, $payment);
            case 'vietqr':
                return $this->paymentVIETQR($booking, $money, $payment);
            case 'viettel_monney':
                return $this->paymentVIETTELMONEY($booking, $money, $payment);
            case 'payoo':
                return $this->paymentPAYOO($booking, $money, $payment);
            case 'mastercard':
                return $this->paymentMasterCard($booking, $money, $payment); //MasterCard
            case 'visa':
                return $this->paymentVISA($booking, $money, $payment); //VISA
            default:
                return response()->json(['error' => 'Phương thức thanh toán không hợp lệ'], 400);
        }
    }


    public function paymentNCB($booking, $money, $payment)
    {
        // Cấu hình của VNPAY
        $vnp_TmnCode = "0749VTZ7"; // Thay bằng mã TmnCode thực tế của bạn
        $vnp_HashSecret = "TTUJCPICUHRHA8PY7LLIQSCZU9Q7ND8U"; // Thay bằng mã HashSecret thực tế của bạn
        $vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
        $vnp_ReturnUrl = "http://localhost:5173/api/payment/NCB-return"; // URL xử lý sau khi thanh toán
        //$vnp_ReturnUrl = "http://localhost:8000/api/payment/NCB-return"; // URL xử lý sau khi thanh toán

        $vnp_TxnRef = $booking->id; // Mã đơn hàng
        $vnp_OrderInfo = "Thanh toán booking ID: " . $booking->id;
        $vnp_OrderType = "billpayment";
        $vnp_Amount = intval($money * 100); // Đơn vị tính là đồng, nhân 100 để đúng định dạng
        $vnp_Locale = "vn";
        $vnp_IpAddr = $_SERVER['REMOTE_ADDR'];
        $vnp_BankCode = "NCB"; // Mã ngân hàng demo để chuyển đến giao diện nhập thẻ

        // Dữ liệu cần gửi cho VNPAY
        $inputData = array(
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $vnp_IpAddr,
            "vnp_Locale" => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => $vnp_ReturnUrl,
            "vnp_TxnRef" => $vnp_TxnRef,
            "vnp_BankCode" => $vnp_BankCode // Truyền mã ngân hàng vào đây
        );

        // Sắp xếp các tham số và tạo chuỗi query
        ksort($inputData);
        $query = "";
        $i = 0;
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        // Tạo hash và thêm vào URL
        $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
        $vnp_Url = $vnp_Url . "?" . $query . 'vnp_SecureHash=' . $vnpSecureHash;

        // Lưu thông tin thanh toán vào cơ sở dữ liệu
        $payment->ma_thanh_toan = $vnp_TxnRef;
        $payment->booking_id = $booking->id;
        $payment->chi_tiet_giao_dich = json_encode($inputData);
        $payment->save();

        return response()->json([
            'message' => 'Chuyển hướng đến trang thanh toán VNPAY',
            'url' => $vnp_Url,

        ]);
    }

    public function NCBReturn(Request $request)
    {
        $vnp_HashSecret = "TTUJCPICUHRHA8PY7LLIQSCZU9Q7ND8U";

        // Lấy tất cả dữ liệu từ request
        $inputData = $request->all();
        $vnp_SecureHash = $inputData['vnp_SecureHash'] ?? '';

        // Kiểm tra nếu `vnp_SecureHash` không có trong request
        if (!$vnp_SecureHash) {
            return response()->json(['message' => 'Thiếu dữ liệu vnp_SecureHash'], 400);
        }

        // Xóa khóa `vnp_SecureHash` khỏi dữ liệu để tính toán hash
        unset($inputData['vnp_SecureHash']);

        // Sắp xếp lại mảng dữ liệu theo thứ tự tăng dần của khóa
        ksort($inputData);

        // Tạo chuỗi dữ liệu để hash
        $hashData = http_build_query($inputData, '', '&');


        // Tính toán SecureHash từ chuỗi dữ liệu và khóa bí mật
        $secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);

        // Kiểm tra SecureHash có khớp không và mã phản hồi từ VNPAY
        if ($secureHash === $vnp_SecureHash) {
            if ($inputData['vnp_ResponseCode'] == '00') {

                // Giao dịch thành công

                // Tìm giao dịch thanh toán dựa trên mã thanh toán
                $payment = Payment::where('ma_thanh_toan', $inputData['vnp_TxnRef'])->first();

                if ($payment) {
                    // trạng thái thanh toán thành công
                    $payment->trang_thai = 'Đã hoàn thành';
                    $payment->save();
                } else {
                    return response()->json(['message' => 'Không tìm thấy giao dịch thanh toán'], 404);
                }

                // Tìm booking dựa trên mã giao dịch
                $booking = Booking::find($inputData['vnp_TxnRef']);

                if ($booking) {

                    $booking->trang_thai = 2; // Cập nhật trạng thái thành công ở booking
                    $booking->save();
                }

                BookingDetail::insert([
                    'booking_id' => $booking->id,
                    'payment_id' => $payment->id
                ]);


                return response()->json(['message' => 'Thanh toán thành công']);
            } else {
                // Xử lý trường hợp `vnp_ResponseCode` không phải '00'
                return response()->json([
                    'message' => 'Thanh toán thất bại',
                    'error_code' => $inputData['vnp_ResponseCode'],
                    'error_message' => $this->getVnpayErrorMessage($inputData['vnp_ResponseCode'])
                ], 400);
            }
        } else {
            // Trả về phản hồi thất bại nếu không khớp SecureHash
            return response()->json(['message' => 'Xác thực chữ ký thất bại'], 400);
        }
    }



    public function paymentVISA($booking, $money, $payment)
    {

        // Cấu hình của VNPAY
        $vnp_TmnCode = "0749VTZ7"; // Thay bằng mã TmnCode thực tế của bạn
        $vnp_HashSecret = "TTUJCPICUHRHA8PY7LLIQSCZU9Q7ND8U"; // Thay bằng mã HashSecret thực tế của bạn
        $vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
        $vnp_ReturnUrl = "http://localhost:5173/api/payment/Visa-return"; // URL xử lý sau khi thanh toán
        //$vnp_ReturnUrl = "http://localhost:8000/api/payment/Visa-return"; // URL xử lý sau khi thanh toán

        $vnp_TxnRef = $booking->id; // Mã đơn hàng
        $vnp_OrderInfo = "Thanh toán booking ID: " . $booking->id;
        $vnp_OrderType = "billpayment";
        $vnp_Amount = intval($money * 100); // Đơn vị tính là đồng, nhân 100 để đúng định dạng
        $vnp_Locale = "vn";
        $vnp_IpAddr = $_SERVER['REMOTE_ADDR'];
        $vnp_BankCode = "VISA"; // Mã ngân hàng demo để chuyển đến giao diện nhập thẻ

        // Dữ liệu cần gửi cho VNPAY
        $inputData = array(
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $vnp_IpAddr,
            "vnp_Locale" => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => $vnp_ReturnUrl,
            "vnp_TxnRef" => $vnp_TxnRef,
            "vnp_BankCode" => $vnp_BankCode // Truyền mã ngân hàng vào đây
        );

        // Sắp xếp các tham số và tạo chuỗi query
        ksort($inputData);
        $query = "";
        $i = 0;
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        // Tạo hash và thêm vào URL
        $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
        $vnp_Url = $vnp_Url . "?" . $query . 'vnp_SecureHash=' . $vnpSecureHash;

        // Lưu thông tin thanh toán vào cơ sở dữ liệu
        $payment->ma_thanh_toan = $vnp_TxnRef;
        $payment->booking_id = $booking->id;
        $payment->chi_tiet_giao_dich = json_encode($inputData);
        $payment->save();

        return response()->json([
            'message' => 'Chuyển hướng đến trang thanh toán Visa',
            'url' => $vnp_Url,

        ]);
    }
    public function visaReturn(Request $request)
    {
        $vnp_HashSecret = "TTUJCPICUHRHA8PY7LLIQSCZU9Q7ND8U";

        // Lấy tất cả dữ liệu từ request
        $inputData = $request->all();
        $vnp_SecureHash = $inputData['vnp_SecureHash'] ?? '';

        // Kiểm tra nếu `vnp_SecureHash` không có trong request
        if (!$vnp_SecureHash) {
            return response()->json(['message' => 'Thiếu dữ liệu vnp_SecureHash'], 400);
        }

        // Xóa khóa `vnp_SecureHash` khỏi dữ liệu để tính toán hash
        unset($inputData['vnp_SecureHash']);

        // Sắp xếp lại mảng dữ liệu theo thứ tự tăng dần của khóa
        ksort($inputData);

        // Tạo chuỗi dữ liệu để hash
        $hashData = http_build_query($inputData, '', '&');


        // Tính toán SecureHash từ chuỗi dữ liệu và khóa bí mật
        $secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);

        // Kiểm tra SecureHash có khớp không và mã phản hồi từ VNPAY
        if ($secureHash === $vnp_SecureHash) {
            if ($inputData['vnp_ResponseCode'] == '00') {

                // Giao dịch thành công

                // Tìm giao dịch thanh toán dựa trên mã thanh toán
                $payment = Payment::where('ma_thanh_toan', $inputData['vnp_TxnRef'])->first();

                if ($payment) {
                    // trạng thái thanh toán thành công
                    $payment->trang_thai = 'Đã hoàn thành';
                    $payment->save();
                } else {
                    return response()->json(['message' => 'Không tìm thấy giao dịch thanh toán'], 404);
                }

                // Tìm booking dựa trên mã giao dịch
                $booking = Booking::find($inputData['vnp_TxnRef']);

                if ($booking) {

                    $booking->trang_thai = 2; // Cập nhật trạng thái thành công ở booking
                    $booking->save();
                }

                BookingDetail::insert([
                    'booking_id' => $booking->id,
                    'payment_id' => $payment->id
                ]);


                return response()->json(['message' => 'Thanh toán thành công']);
            } else {
                // Xử lý trường hợp `vnp_ResponseCode` không phải '00'
                return response()->json([
                    'message' => 'Thanh toán thất bại',
                    'error_code' => $inputData['vnp_ResponseCode'],
                    'error_message' => $this->getVnpayErrorMessage($inputData['vnp_ResponseCode'])
                ], 400);
            }
        } else {
            // Trả về phản hồi thất bại nếu không khớp SecureHash
            return response()->json(['message' => 'Xác thực chữ ký thất bại'], 400);
        }
    }


    public function paymentMasterCard($booking, $money, $payment)
    {

        // Cấu hình của VNPAY
        $vnp_TmnCode = "0749VTZ7"; // Thay bằng mã TmnCode thực tế của bạn
        $vnp_HashSecret = "TTUJCPICUHRHA8PY7LLIQSCZU9Q7ND8U"; // Thay bằng mã HashSecret thực tế của bạn
        $vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
        //$vnp_ReturnUrl = "http://localhost:8000/api/payment/MasterCard-return"; // URL xử lý sau khi thanh toán
        $vnp_ReturnUrl = "http://localhost:5173/api/payment/MasterCard-return"; // URL xử lý sau khi thanh toán

        $vnp_TxnRef = $booking->id; // Mã đơn hàng
        $vnp_OrderInfo = "Thanh toán booking ID: " . $booking->id;
        $vnp_OrderType = "billpayment";
        $vnp_Amount = intval($money * 100); // Đơn vị tính là đồng, nhân 100 để đúng định dạng
        $vnp_Locale = "vn";
        $vnp_IpAddr = $_SERVER['REMOTE_ADDR'];
        $vnp_BankCode = "MasterCard"; // Mã ngân hàng demo để chuyển đến giao diện nhập thẻ

        // Dữ liệu cần gửi cho VNPAY
        $inputData = array(
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $vnp_IpAddr,
            "vnp_Locale" => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => $vnp_ReturnUrl,
            "vnp_TxnRef" => $vnp_TxnRef,
            "vnp_BankCode" => $vnp_BankCode // Truyền mã ngân hàng vào đây
        );

        // Sắp xếp các tham số và tạo chuỗi query
        ksort($inputData);
        $query = "";
        $i = 0;
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        // Tạo hash và thêm vào URL
        $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
        $vnp_Url = $vnp_Url . "?" . $query . 'vnp_SecureHash=' . $vnpSecureHash;

        // Lưu thông tin thanh toán vào cơ sở dữ liệu
        $payment->ma_thanh_toan = $vnp_TxnRef;
        $payment->booking_id = $booking->id;
        $payment->chi_tiet_giao_dich = json_encode($inputData);
        $payment->save();

        return response()->json([
            'message' => 'Chuyển hướng đến trang thanh toán MasterCard',
            'url' => $vnp_Url,

        ]);
    }
    public function mastercardReturn(Request $request)
    {
        $vnp_HashSecret = "TTUJCPICUHRHA8PY7LLIQSCZU9Q7ND8U";

        // Lấy tất cả dữ liệu từ request
        $inputData = $request->all();
        $vnp_SecureHash = $inputData['vnp_SecureHash'] ?? '';

        // Kiểm tra nếu `vnp_SecureHash` không có trong request
        if (!$vnp_SecureHash) {
            return response()->json(['message' => 'Thiếu dữ liệu vnp_SecureHash'], 400);
        }

        // Xóa khóa `vnp_SecureHash` khỏi dữ liệu để tính toán hash
        unset($inputData['vnp_SecureHash']);

        // Sắp xếp lại mảng dữ liệu theo thứ tự tăng dần của khóa
        ksort($inputData);

        // Tạo chuỗi dữ liệu để hash
        $hashData = http_build_query($inputData, '', '&');


        // Tính toán SecureHash từ chuỗi dữ liệu và khóa bí mật
        $secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);

        // Kiểm tra SecureHash có khớp không và mã phản hồi từ VNPAY
        if ($secureHash === $vnp_SecureHash) {
            if ($inputData['vnp_ResponseCode'] == '00') {

                // Giao dịch thành công

                // Tìm giao dịch thanh toán dựa trên mã thanh toán
                $payment = Payment::where('ma_thanh_toan', $inputData['vnp_TxnRef'])->first();

                if ($payment) {
                    // trạng thái thanh toán thành công
                    $payment->trang_thai = 'Đã hoàn thành';
                    $payment->save();
                } else {
                    return response()->json(['message' => 'Không tìm thấy giao dịch thanh toán'], 404);
                }

                // Tìm booking dựa trên mã giao dịch
                $booking = Booking::find($inputData['vnp_TxnRef']);

                if ($booking) {

                    $booking->trang_thai = 2; // Cập nhật trạng thái thành công ở booking
                    $booking->save();
                }

                BookingDetail::insert([
                    'booking_id' => $booking->id,
                    'payment_id' => $payment->id
                ]);


                return response()->json(['message' => 'Thanh toán thành công']);
            } else {
                // Xử lý trường hợp `vnp_ResponseCode` không phải '00'
                return response()->json([
                    'message' => 'Thanh toán thất bại',
                    'error_code' => $inputData['vnp_ResponseCode'],
                    'error_message' => $this->getVnpayErrorMessage($inputData['vnp_ResponseCode'])
                ], 400);
            }
        } else {
            // Trả về phản hồi thất bại nếu không khớp SecureHash
            return response()->json(['message' => 'Xác thực chữ ký thất bại'], 400);
        }
    }


    private function getVnpayErrorMessage($code)
    {
        $errors = [
            '01' => 'Giao dịch đã tồn tại',
            '02' => 'Merchant không hợp lệ',
            '03' => 'Dữ liệu gửi không đầy đủ',
            '04' => 'Khóa bí mật không hợp lệ',

        ];

        return $errors[$code] ?? 'Lỗi không xác định';
    }

    public function paymentVIETQR($booking, $money, $payment) {}
    public function vietqrReturn(Request $request) {}


    public function paymentVIETTELMONEY($booking, $money, $payment) {}
    public function viettelmoneyReturn(Request $request) {}


    public function paymentPAYOO($booking, $money, $payment) {}
    public function payooReturn(Request $request) {}



    public function createPayment1($registerMemberId, $method)
    {
        // $user = auth()->user();
        // if (!$user) {
        //     return response()->json([
        //         'message' => 'Chưa đăng nhập phải đăng nhập'
        //     ], 401);
        // }

        $registerMember = RegisterMember::find($registerMemberId); // Changed Booking to RegisterMember
        if (!$registerMember) {
            return response()->json(['message' => 'No register member found'], 404);
        }

        // Check if payment has already been made
        if ($registerMember->trang_thai !== 0) {
            return response()->json(['error' => 'Payment has already been processed for this register member'], 400);
        }

        $money = $registerMember->tong_tien;

        $payment = new Payment();
        $payment->registermember_id = $registerMember->id; // Changed from booking_id to registermember_id
        $payment->tong_tien = $money;
        $payment->phuong_thuc_thanh_toan = $method;
        $payment->trang_thai = 'Đang chờ xử lý';
        $payment->ngay_thanh_toan = Carbon::now();
        $payment->save();

        // Handle different payment methods
        switch ($method) {
            case 'ncb':
                return $this->paymentNCB1($registerMember, $money, $payment); // Changed booking to registerMember
            case 'vietqr':
                return $this->paymentVIETQR($registerMember, $money, $payment); // Changed booking to registerMember
            case 'viettel_money':
                return $this->paymentVIETTELMONEY($registerMember, $money, $payment); // Changed booking to registerMember
            case 'payoo':
                return $this->paymentPAYOO($registerMember, $money, $payment); // Changed booking to registerMember
            case 'mastercard':
                return $this->paymentMasterCard($registerMember, $money, $payment); // Changed booking to registerMember
            case 'visa':
                return $this->paymentVISA($registerMember, $money, $payment); // Changed booking to registerMember
            default:
                return response()->json(['error' => 'Invalid payment method'], 400);
        }
    }

    public function paymentNCB1($registerMember, $money, $payment)
    {
        // VNPAY configuration
        $vnp_TmnCode = "0749VTZ7"; // Your actual TmnCode
        $vnp_HashSecret = "TTUJCPICUHRHA8PY7LLIQSCZU9Q7ND8U"; // Your actual HashSecret
        $vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
        $vnp_ReturnUrl = "http://localhost:8000/api/payment/NCB-return1"; // URL to handle payment response

        $vnp_TxnRef = $registerMember->id . '_' . time(); 

        $vnp_OrderInfo = "Thanh toán register member ID: " . $registerMember->id;
        $vnp_OrderType = "billpayment";
        $vnp_Amount = intval($money * 100); // Convert to the correct unit (Vietnamese đồng)
        $vnp_Locale = "vn";
        $vnp_IpAddr = $_SERVER['REMOTE_ADDR'];
        $vnp_BankCode = "NCB"; // Example bank code

        // Prepare data to send to VNPAY
        $inputData = array(
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $vnp_IpAddr,
            "vnp_Locale" => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => $vnp_ReturnUrl,
            "vnp_TxnRef" => $vnp_TxnRef,
            "vnp_BankCode" => $vnp_BankCode // Bank code
        );

        // Sort parameters and create query string
        ksort($inputData);
        $query = "";
        $i = 0;
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        // Generate hash and append to the URL
        $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
        $vnp_Url = $vnp_Url . "?" . $query . 'vnp_SecureHash=' . $vnpSecureHash;

        // Save payment details to database
        $payment->ma_thanh_toan = $vnp_TxnRef;
        $payment->registermember_id = $registerMember->id; // Changed booking_id to registermember_id
        $payment->chi_tiet_giao_dich = json_encode($inputData);
        $payment->save();

        return response()->json([
            'message' => 'Redirecting to VNPAY payment page',
            'url' => $vnp_Url,
        ]);
    }


    public function NCBReturn1(Request $request)
    {
        $vnp_HashSecret = "TTUJCPICUHRHA8PY7LLIQSCZU9Q7ND8U";

        // Get all input data from the request
        $inputData = $request->all();
        $vnp_SecureHash = $inputData['vnp_SecureHash'] ?? '';

        // Check if vnp_SecureHash is missing
        if (!$vnp_SecureHash) {
            return response()->json(['message' => 'Missing vnp_SecureHash data'], 400);
        }

        // Remove vnp_SecureHash from the data to calculate the hash
        unset($inputData['vnp_SecureHash']);

        // Sort the input data by keys
        ksort($inputData);

        // Build the string for hash calculation
        $hashData = http_build_query($inputData, '', '&');

        // Calculate the secure hash using the secret key
        $secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);

        // Check if the secure hash matches and verify the response code
        if ($secureHash === $vnp_SecureHash) {
            if ($inputData['vnp_ResponseCode'] == '00') {

                // Successful payment

                // Find the payment record by transaction reference
                $payment = Payment::where('ma_thanh_toan', $inputData['vnp_TxnRef'])->first();

                if ($payment) {
                    $payment->trang_thai = 'Đã hoàn thành'; // Update payment status
                    $payment->save();
                } else {
                    return response()->json(['message' => 'Payment not found'], 404);
                }

                // Find the registerMember using the transaction reference
                $registerMember = RegisterMember::find($inputData['vnp_TxnRef']);

                if ($registerMember) {
                    $registerMember->trang_thai = 2; // Update register member status to completed
                    $registerMember->save();
                }



                return response()->json(['message' => 'Payment successful']);
            } else {
                // Handle failure response code
                return response()->json([
                    'message' => 'Payment failed',
                    'error_code' => $inputData['vnp_ResponseCode'],
                    'error_message' => $this->getVnpayErrorMessage($inputData['vnp_ResponseCode'])
                ], 400);
            }
        } else {
            return response()->json(['message' => 'Secure hash validation failed'], 400);
        }
    }
}
