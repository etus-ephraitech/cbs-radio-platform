<?php

namespace App\Controllers;

use App\Libraries\VoiceNoteUploader;
use App\Models\BookingModel;
use App\Models\CategoryModel;
use App\Models\MessageTemplateModel;
use App\Models\RateTierModel;
use App\Services\BookingService;
use App\Services\PaymentService;
use App\Services\SchedulingService;

/**
 * Public booking flow: category -> package -> message/voice note ->
 * schedule -> submit. No account required — bookings are guest
 * submissions identified by sender_phone.
 */
class BookingController extends BaseController
{
    protected CategoryModel $categoryModel;
    protected RateTierModel $rateTierModel;
    protected BookingService $bookingService;
    protected SchedulingService $schedulingService;
    protected PaymentService $paymentService;
    protected VoiceNoteUploader $voiceNoteUploader;

    public function __construct()
    {
        $this->categoryModel     = new CategoryModel();
        $this->rateTierModel     = new RateTierModel();
        $this->bookingService    = new BookingService();
        $this->schedulingService = new SchedulingService();
        $this->paymentService    = new PaymentService();
        $this->voiceNoteUploader = new VoiceNoteUploader();
    }

    /**
     * Step 1: category grid (homepage "Send a Greeting" quick action
     * lands here).
     */
    public function index()
    {
        return view('booking/categories', [
            'categories' => $this->categoryModel->active(),
        ]);
    }

    /**
     * Step 2: package selection + message builder form for one category.
     */
    public function category(string $slug)
    {
        $category = $this->categoryModel->findBySlug($slug);

        // Hidden from the list, so it must not be reachable by typing its address either: the visitor would fill
        // the whole form in and only then be told it is not available.
        if ($category === null || (int) $category['is_active'] !== 1) {
            return redirect()->to(site_url('booking'))->with('toastr_error', 'Category not found.');
        }

        $isoDayOfWeek = (int) date('N', strtotime('today'));

        return view('booking/create', [
            'category'        => $category,
            'rateTiers'       => $this->rateTierModel->forCategory($category['id']),
            'shows'           => $this->schedulingService->showsForDay($isoDayOfWeek),
            'config'          => config('CbsRadio'),
            // Only what the form's script needs; everything is escaped when it is put on the page.
            'templates'       => array_map(
                static fn (array $t): array => ['id' => (int) $t['id'], 'title' => $t['title'], 'language' => $t['language'], 'body' => $t['body']],
                (new MessageTemplateModel())->forCategory((int) $category['id'])
            ),
        ]);
    }

    /**
     * Step 3: handle the submitted booking form, including the optional
     * voice note upload.
     */
    public function store()
    {
        $rules = [
            'category_id'     => 'required|is_natural_no_zero',
            'rate_tier_id'    => 'required|is_natural_no_zero',
            'sender_name'     => 'required|max_length[150]',
            'sender_phone'    => 'required|max_length[20]',
            'sender_email'    => 'permit_empty|valid_email',
            'recipient_name'  => 'required|max_length[150]',
            'message_text'    => 'required',
            'show_id'         => 'permit_empty|is_natural_no_zero',
            'scheduled_date'  => 'permit_empty|valid_date[Y-m-d]', // not bare valid_date: it accepts "next friday" and 2026-02-30
            // The voice note is deliberately not validated here: VoiceNoteUploader checks it
            // and explains any problem in plain words (the framework's own wording is not friendly).
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $input = $this->request->getPost([
            'category_id', 'rate_tier_id', 'sender_name', 'sender_phone',
            'sender_email', 'recipient_name', 'message_text', 'show_id', 'scheduled_date',
        ]);

        $voiceNote = $this->request->getFile('voice_note');

        // "No file chosen" is fine. Anything else — including an upload PHP itself
        // rejected, such as one over the server's size limit — is checked and
        // reported, rather than being quietly dropped and the booking made without it.
        if ($voiceNote !== null && $voiceNote->getError() !== UPLOAD_ERR_NO_FILE) {
            $clientDuration = $this->request->getPost('voice_note_duration_seconds');
            $clientDuration = is_numeric($clientDuration) ? (int) $clientDuration : null;

            $check = $this->voiceNoteUploader->validate($voiceNote, $clientDuration);

            if (! $check['valid']) {
                return redirect()->back()->withInput()->with('toastr_error', $check['message']);
            }

            $input['voice_note_path']             = $this->voiceNoteUploader->store($voiceNote);
            $input['voice_note_duration_seconds'] = $check['duration']; // measured, when the server can measure it
        }

        $result = $this->bookingService->createBooking($input);

        if (! $result['success']) {
            // The booking didn't happen, so don't leave its audio file behind.
            if (! empty($input['voice_note_path'])) {
                $this->voiceNoteUploader->delete($input['voice_note_path']);
            }

            return redirect()->back()->withInput()->with('toastr_error', $result['message']);
        }

        return redirect()->to(site_url('booking/' . $result['booking']['id'] . '/pay'));
    }

    /**
     * Step 4: kick off payment for the booking just created.
     */
    public function pay(int $bookingId)
    {
        $booking = (new BookingModel())->find($bookingId);

        if ($booking === null) {
            return redirect()->to(site_url('booking'))->with('toastr_error', 'Booking not found.');
        }

        // Opening this page starts a payment, so only do that for a booking
        // that is actually waiting to be paid — not one that is already paid,
        // approved, aired, or cancelled.
        if ($booking['status'] !== BookingModel::STATUS_PENDING_PAYMENT) {
            return redirect()->to(site_url('booking/' . $bookingId . '/confirmation'));
        }

        $result = $this->paymentService->initiateForBooking($booking);

        return view('booking/pay', [
            'booking'  => $booking,
            'result'   => $result,
            'payments' => config('Payments'),
            'contact'  => config('CbsRadio'),
        ]);
    }

    /**
     * Confirmation / status page, linkable from the SMS confirmation.
     */
    public function confirmation(int $bookingId)
    {
        $booking = (new BookingModel())->find($bookingId);

        if ($booking === null) {
            return redirect()->to(site_url('booking'))->with('toastr_error', 'Booking not found.');
        }

        return view('booking/confirmation', ['booking' => $booking]);
    }
}
