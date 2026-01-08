<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketsController extends Controller
{
    /**
     * Danh sách đơn vé (Admin)
     */
    public function index(Request $request)
    {
        // Khởi tạo query từ bảng tickets
        $query = DB::table('tickets as t')
            ->join('users as u', 'u.id', '=', 't.user_id')
            ->join('showtimes as st', 'st.id', '=', 't.showtime_id')
            ->join('movies as m', 'm.id', '=', 'st.movie_id')
            ->join('rooms as r', 'r.id', '=', 'st.room_id')
            ->join('seats as s', 's.id', '=', 't.seat_id') // <--- QUAN TRỌNG: Join bảng ghế
            ->select(
                't.*',
                'u.name as user_name',
                'u.email as user_email',
                'm.title as movie_title',
                'r.name as room_name',
                'st.start_time',
                // Tạo seat_label từ row_letter và seat_number (VD: A1, B12)
                DB::raw("CONCAT(s.row_letter, s.seat_number) as seat_label")
            );

        // --- BỘ LỌC ---

        // 1. Lọc theo trạng thái
        if ($request->filled('status')) {
            $query->where('t.status', $request->status);
        }

        // 2. Lọc theo ngày chiếu
        if ($request->filled('date')) {
            $query->whereDate('st.start_time', $request->date);
        }

        // 3. Tìm kiếm (Tên khách, Email, Tên phim, Mã vé)
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function($sub) use ($q) {
                $sub->where('u.name', 'like', "%{$q}%")
                    ->orWhere('u.email', 'like', "%{$q}%")
                    ->orWhere('m.title', 'like', "%{$q}%")
                    ->orWhere('t.qr_code', 'like', "%{$q}%");
            });
        }

        // Sắp xếp vé mới nhất lên đầu và phân trang
        $tickets = $query->orderByDesc('t.created_at')->paginate(15)->withQueryString();

        return view('admin.tickets.index', compact('tickets'));
    }

    /**
     * Hủy vé (Chuyển trạng thái sang canceled)
     */
    public function cancel($id)
    {
        DB::table('tickets')->where('id', $id)->update([
            'status' => 'canceled',
            'updated_at' => now()
        ]);

        return back()->with('success', 'Đã hủy vé thành công.');
    }

    /**
     * Hoàn tiền (Demo chức năng)
     */
    public function refund($id)
    {
        // Thực tế sẽ cần logic hoàn tiền qua cổng thanh toán
        // Ở đây chỉ cập nhật trạng thái
        DB::table('tickets')->where('id', $id)->update([
            'status' => 'refunded', // Hoặc trạng thái phù hợp
            'updated_at' => now()
        ]);

        return back()->with('success', 'Đã xác nhận hoàn tiền.');
    }

    /**
     * Trang sửa vé (Nếu cần)
     */
    public function edit($id)
    {
        $ticket = DB::table('tickets as t')
            ->join('seats as s', 's.id', '=', 't.seat_id')
            ->select('t.*', DB::raw("CONCAT(s.row_letter, s.seat_number) as seat_label"))
            ->where('t.id', $id)
            ->first();

        if (!$ticket) {
            return back()->with('error', 'Không tìm thấy vé.');
        }

        return view('admin.tickets.edit', compact('ticket'));
    }
}
