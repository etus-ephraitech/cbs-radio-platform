<?php

namespace App\Controllers\Admin;

use App\Libraries\FileManager;
use App\Models\BookingModel;
use App\Models\CategoryModel;
use App\Models\GiftOrderModel;
use App\Models\ModerationLogModel;
use App\Models\NotificationsLogModel;
use App\Models\PaymentModel;
use App\Services\BookingService;

/**
 * Admin view of every booking: a searchable, filterable list, and a
 * detail page that pulls together everything attached to one booking
 * (payments, gift orders, moderation history, notifications) with the
 * actions available at its current status. Approve/reject and manual
 * mark-paid reuse the existing moderation and payment routes — this
 * controller adds only what those don't cover (viewing, voice note
 * playback, cancelling).
 */
class BookingController extends BaseAdminController
{
    protected BookingModel $bookingModel;
    protected BookingService $bookingService;
    protected FileManager $fileManager;

    public function __construct()
    {
        $this->bookingModel   = new BookingModel();
        $this->bookingService = new BookingService();
        $this->fileManager    = new FileManager();
    }

    public function index()
    {
        $perPage = config('CbsRadio')->adminPageSize;

        $filters = $this->readFilters();

        $total      = $this->bookingModel->adminCount($filters);
        $totalPages = max((int) ceil($total / $perPage), 1);
        $page       = min(max((int) $this->request->getGet('page'), 1), $totalPages);

        return ui_view('admin/bookings/index', [
            'bookings'   => $this->bookingModel->adminSearch($filters, $perPage, ($page - 1) * $perPage),
            'categories' => (new CategoryModel())->orderBy('name', 'ASC')->findAll(),
            'statuses'   => BookingModel::STATUSES,
            'filters'    => $filters,
            'page'       => $page,
            'totalPages' => $totalPages,
            'total'      => $total,
        ]);
    }

    public function show(int $bookingId)
    {
        $booking = $this->bookingModel->adminFind($bookingId);

        if ($booking === null) {
            return redirect()->to(site_url('admin/bookings'))->with('toastr_error', 'Booking not found.');
        }

        return ui_view('admin/bookings/show', [
            'booking'       => $booking,
            'payments'      => (new PaymentModel())->where('booking_id', $bookingId)->orderBy('created_at', 'ASC')->findAll(),
            'giftOrders'    => (new GiftOrderModel())->forBookingWithGift($bookingId),
            'moderation'    => (new ModerationLogModel())->forBooking($bookingId),
            'notifications' => (new NotificationsLogModel())->forBooking($bookingId),
        ]);
    }

    /**
     * Streams the booking's voice note for in-page playback. writable/ is
     * not web-accessible by design, so this route is the only way to
     * play it back — see FileManager.
     */
    public function voiceNote(int $bookingId)
    {
        $booking = $this->bookingModel->find($bookingId);

        if ($booking === null || empty($booking['voice_note_path'])) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->fileManager->streamInline($this->response, $booking['voice_note_path']);
    }

    /**
     * Cancels a booking and releases its show-slot spot. Does NOT refund
     * any payment already taken — there is no refund flow in Phase 1, so
     * that stays a manual, off-platform step.
     */
    public function cancel(int $bookingId)
    {
        $result = $this->bookingService->cancelBooking($bookingId);

        return redirect()->back()->with(
            $result['success'] ? 'toastr_success' : 'toastr_error',
            $result['message']
        );
    }

    /**
     * Reads and whitelists the list filters from the query string —
     * they feed WHERE clauses, so nothing is passed through unchecked.
     *
     * @return array{q: ?string, status: ?string, category_id: ?int, date_from: ?string, date_to: ?string}
     */
    protected function readFilters(): array
    {
        $q      = trim((string) $this->request->getGet('q'));
        $status = $this->request->getGet('status');
        $cat    = $this->request->getGet('category_id');

        return [
            'q'           => $q !== '' ? mb_substr($q, 0, 100) : null,
            'status'      => in_array($status, BookingModel::STATUSES, true) ? $status : null,
            'category_id' => ctype_digit((string) $cat) && (int) $cat > 0 ? (int) $cat : null,
            'date_from'   => $this->validDate($this->request->getGet('date_from')),
            'date_to'     => $this->validDate($this->request->getGet('date_to')),
        ];
    }

    protected function validDate(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }
}
