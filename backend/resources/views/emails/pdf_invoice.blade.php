<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vé Xem Phim</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            /* Font hỗ trợ tiếng Việt */
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }

        .ticket {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            box-sizing: border-box;
        }

        .header {
            text-align: center;
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .sub-header {
            text-align: center;
            font-size: 14px;

            margin-bottom: 20px;
        }

        .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .row span {
            font-size: 12px;
        }

        .content {
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 10px 0;
            margin-bottom: 10px;
        }

        .barcode {
            text-align: center;
            margin-top: 20px;
        }

        .barcode img {
            width: 100px;
            height: auto;
        }

        .footer {
            text-align: center;
            font-size: 10px;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="ticket">
        <div class="header">CineBookingHub</div>
        <div class="sub-header">Vé Phim</div>

        <div class="row">
            <strong>Rạp:</strong>
            <strong>CineBookingHub</strong>
        </div>
        <div class="row">
            <strong>Địa chỉ:</strong>
            <span>Tầng 1, Tòa Nhà Thương Mại 5, Xuân Phương, Nam Từ Liêm, Hà Nội</span>
        </div>

        <div class="content">
            <div class="row">
                <span>Phim:</span>
                <span>{{ $booking->showtime->movie->ten_phim }}</span>
            </div>
            <div class="row">
                <span>Thời gian phim:</span>
                <span>{{ $booking->showtime->thoi_luong_chieu }} phút</span>
            </div>
            <div class="row">
                <span>Ngày xem phim:</span>
                <span> {{ \Carbon\Carbon::parse($booking->showtime->ngay_chieu)->format('d-m-Y') }} </span>
            </div>
            <div class="row">
                <span>Giờ xem phim:</span>
                <span> {{ \Carbon\Carbon::parse($booking->showtime->gio_chieu)->format('H:i') }} </span>
            </div>
            <div class="row">
                <span>Phòng chiếu:</span>
                <span>{{ $room->ten_phong_chieu }}</span>
            </div>
            <div class="row">
                <span>Ghế:</span>
                <span>{{ $booking->ghe_ngoi }}</span>
            </div>
            <div class="row">
                <span>Số lượng vé/người:</span>
                <span>{{ $booking->so_luong }}</span>
            </div>
            <div class="row">
                <span>Đồ ăn kèm:</span>
                <span>{{ $booking->do_an }}</span>
            </div>
            <div class="row">
                <span>Ngày mua:</span>
                <span> {{ \Carbon\Carbon::parse($booking->ngay_mua)->format('d-m-Y') }} </span>
            </div>
            <div class="row">
                <span>Ghi chú:</span>
                <span>{{ $booking->ghi_chu }}</span>
            </div>
        </div>

        <div class="row">
            <strong>Tổng thanh toán:</strong>
            <strong>{{ number_format($booking->tong_tien_thanh_toan) }} VND</strong>
        </div>

        <div class="row" style="margin-top: 20px; white-space: nowrap;">
            <p>Mã Barcode vé phim</p>

            {!! $barcode !!}

            <p>{{ $booking->barcode }}</p>
        </div>

        <div class="footer">
            Ticket No. | Cảm ơn bạn đã chọn CineBookingHub!
        </div>
    </div>


</body>

</html>
