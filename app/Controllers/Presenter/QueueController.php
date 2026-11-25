<?php

namespace App\Controllers\Presenter;

use App\Controllers\Admin\BaseAdminController;
use App\Libraries\FileManager;
use App\Models\BookingModel;
use App\Services\BookingService;
use App\Services\PresenterQueueService;

/**
 * Presenter portal: the live, approved-queue feed a presenter works
 * through on air. Requires an authenticated staff session — see
 * BaseAdminController for the auth assumption this relies on.
 */
class QueueController extends BaseAdminController
{
    protected PresenterQueueService $queueService;
    protected BookingService $bookingService;
    protected BookingModel $bookingModel;
    protected FileManager $fileManager;

    public function __construct()
    {
        $this->queueService   = new PresenterQueueService();
        $this->bookingService = new BookingService();
        $this->bookingModel   = new BookingModel();
        $this->fileManager    = new FileManager();
    }

    /**
     * Default landing: every active show with today's approved queue.
     */
    public function index()
    {
        return view('presenter/queue_index', [
            'showQueues' => $this->queueService->todaysQueuesByShow(),
        ]);
    }

    /**
     * One show's queue for a chosen date (defaults to today).
     */
    public function show(int $showId)
    {
        $date = $this->request->getGet('date');

        return view('presenter/queue_show', [
            'showId' => $showId,
            'date'   => $date ?? date('Y-m-d'),
            'queue'  => $this->queueService->queueForShow($showId, $date),
        ]);
    }

    /**
     * Marks a booking as aired once the presenter has read it out.
     */
    public function markAired(int $bookingId)
    {
        $ok = $this->bookingService->markAired($bookingId);

        if (! $ok) {
            return redirect()->back()->with('toastr_error', 'Could not mark this booking as aired.');
        }

        return redirect()->back()->with('toastr_success', 'Marked as aired.');
    }

    /**
     * Streams a booking's voice note inline for in-portal playback before
     * reading it on air. writable/ isn't web-accessible by design, so
     * this route is the only way to play it back — see FileManager.
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
