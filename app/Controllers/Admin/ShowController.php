<?php

namespace App\Controllers\Admin;

use App\Models\ShowModel;

/**
 * Admin CRUD for radio shows (e.g. "Morning Express", "Evening Drive") —
 * the time-slots listeners pick from in the booking flow's scheduling step.
 */
class ShowController extends BaseAdminController
{
    protected ShowModel $showModel;

    public function __construct()
    {
        $this->showModel = new ShowModel();
    }

    public function index()
    {
        return view('admin/shows/index', [
            'shows' => $this->showModel->orderBy('start_time', 'ASC')->findAll(),
        ]);
    }

    public function create()
    {
        return view('admin/shows/form', ['show' => null]);
    }

    public function store()
    {
        $rules = $this->validationRules();

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $id = $this->showModel->insert($this->collectInput());

        if ($id === false) {
            return redirect()->back()->withInput()->with('errors', $this->showModel->errors());
        }

        return redirect()->to(site_url('admin/shows'))->with('toastr_success', 'Show created.');
    }

    public function edit(int $id)
    {
        $show = $this->showModel->find($id);

        if ($show === null) {
            return redirect()->to(site_url('admin/shows'))->with('toastr_error', 'Show not found.');
        }

        return view('admin/shows/form', ['show' => $show]);
    }

    public function update(int $id)
    {
        $show = $this->showModel->find($id);

        if ($show === null) {
            return redirect()->to(site_url('admin/shows'))->with('toastr_error', 'Show not found.');
        }

        if (! $this->validate($this->validationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $ok = $this->showModel->update($id, $this->collectInput());

        if (! $ok) {
            return redirect()->back()->withInput()->with('errors', $this->showModel->errors());
        }

        return redirect()->to(site_url('admin/shows'))->with('toastr_success', 'Show updated.');
    }

    /**
     * Shows are deactivated, not deleted — show_slots and bookings
     * reference show_id, and a hard delete would orphan that history.
     */
    public function deactivate(int $id)
    {
        $ok = $this->showModel->update($id, ['is_active' => 0]);

        return redirect()->back()->with(
            $ok ? 'toastr_success' : 'toastr_error',
            $ok ? 'Show deactivated.' : 'Could not deactivate this show.'
        );
    }

    public function activate(int $id)
    {
        $ok = $this->showModel->update($id, ['is_active' => 1]);

        return redirect()->back()->with(
            $ok ? 'toastr_success' : 'toastr_error',
            $ok ? 'Show activated.' : 'Could not activate this show.'
        );
    }

    protected function validationRules(): array
    {
        return [
            'name'       => 'required|max_length[100]',
            'start_time' => 'required',
            'end_time'   => 'required',
        ];
    }

    /**
     * days_of_week arrives as an array of checked day numbers (1=Mon..7=Sun)
     * from the form and is stored as a comma-separated string, matching
     * ShowModel::runsOn()'s expected format. No days checked means the
     * show runs daily (an empty days_of_week column).
     */
    protected function collectInput(): array
    {
        // The form sends a list of ticked boxes, but a hand-made request can send anything: a lone value, junk, days
        // that do not exist. Only real days (1 = Monday ... 7 = Sunday) are kept, each once.
        $days = array_map('intval', (array) $this->request->getPost('days_of_week'));
        $days = array_values(array_unique(array_filter($days, static fn (int $day): bool => $day >= 1 && $day <= 7)));
        sort($days);

        return [
            'name'         => $this->request->getPost('name'),
            'start_time'   => $this->request->getPost('start_time'),
            'end_time'     => $this->request->getPost('end_time'),
            'days_of_week' => empty($days) ? null : implode(',', $days),
            'is_active'    => $this->request->getPost('is_active') ? 1 : 0,
        ];
    }
}
