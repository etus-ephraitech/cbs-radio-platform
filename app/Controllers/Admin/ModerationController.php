<?php

namespace App\Controllers\Admin;

use App\Libraries\FileManager;
use App\Models\BookingModel;
use App\Services\ModerationService;

/**
 * Admin moderation queue: approve, reject, or flag a booking pending
 * review. Every action is logged with the moderator's identity via
 * ModerationService — this controller never touches BookingModel's
 * status directly.
 */
class ModerationController extends BaseAdminController
{
    protected BookingModel $bookingModel;
    protected ModerationService $moderationService;
    protected FileManager $fileManager;

    public function __construct()
    {
        $this->bookingModel      = new BookingModel();
        $this->moderationService = new ModerationService();
        $this->fileManager       = new FileManager();
    }

    public function index()
    {
        return ui_view('admin/moderation/index', [
            'bookings' => $this->bookingModel->pendingModeration(),
        ]);
    }

    public function approve(int $bookingId)
    {
        $notes  = $this->request->getPost('notes');
        $result = $this->moderationService->approve($bookingId, $this->currentUserId(), $notes);

        return redirect()->back()->with(
            $result['success'] ? 'toastr_success' : 'toastr_error',
            $result['message']
        );
    }

    public function reject(int $bookingId)
    {
        $notes  = $this->request->getPost('notes');
        $result = $this->moderationService->reject($bookingId, $this->currentUserId(), $notes);

        return redirect()->back()->with(
            $result['success'] ? 'toastr_success' : 'toastr_error',
            $result['message']
        );
    }

    public function flag(int $bookingId)
    {
        $notes  = $this->request->getPost('notes');
        $result = $this->moderationService->flag($bookingId, $this->currentUserId(), $notes);

        return redirect()->back()->with(
            $result['success'] ? 'toastr_success' : 'toastr_error',
            $result['message']
        );
    }

    /**
     * Full moderation audit trail for one booking (e.g. shown on a detail
     * panel/modal in the queue view).
     */
    public function history(int $bookingId)
    {
        return $this->response->setJSON([
            'history' => $this->moderationService->history($bookingId),
        ]);
    }

    /**
     * Lets a moderator listen to the voice note before deciding.
     */
    public function playVoiceNote(int $bookingId)
    {
        $booking = $this->bookingModel->find($bookingId);

        if ($booking === null || empty($booking['voice_note_path'])) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->fileManager->streamInline($this->response, $booking['voice_note_path']);
    }
}
