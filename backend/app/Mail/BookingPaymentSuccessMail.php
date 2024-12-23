<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Showtime;
use Barryvdh\DomPDF\Facade\Pdf as FacadePdf;
use Barryvdh\DomPDF\PDF;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Milon\Barcode\Facades\DNS1DFacade;
use Milon\Barcode\Facades\DNS2DFacade;


class BookingPaymentSuccessMail extends Mailable
{
    use Queueable, SerializesModels;


    public $booking;
    public $payment;

    public $room;
    public $showtime;


    public function __construct(Booking $booking, Payment $payment)
    {
        $this->booking = $booking;
        $this->payment = $payment;

        $this->showtime = Showtime::with('room')
            ->where('id', $this->booking->thongtinchieu_id)
            ->first();
        $this->room = $this->showtime ? $this->showtime->room : null;
    }

    public function build()
    {


        $qrbarcode = DNS1DFacade::getBarcodeHTML($this->booking->barcode, 'C128');
      
        // Tạo PDF từ view
        $pdf = FacadePdf::loadView('emails.pdf_invoice', [
            'booking' => $this->booking,
            'payment' => $this->payment,
            'room' => $this->room,
            'showtime' => $this->showtime,
            'barcode' => $qrbarcode,
        ]);

        // Set font mặc định DejaVu Sans

        $pdf->setPaper('A5', 'portrait');
        $pdf->setOption('defaultFont', 'dejavusans');

        // Tạo và tải file PDF
        //return $pdf->download('invoice.pdf');

        return $this->subject('Thanh toán thành công - Thông tin chi tiết ')
            ->view('emails.send_bill_payment_success')
            ->with([
                'booking' => $this->booking,
                'payment' => $this->payment,
                'room' => $this->room,
                'showtime' => $this->showtime,
                'barcode' => $qrbarcode,
            ])->attachData($pdf->output(), 've_xem_phim.pdf', [
                'mime' => 'application/pdf',
            ]);
    }
}
