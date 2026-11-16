<?php

namespace App\Controllers\Admin;

use App\Models\CategoryModel;
use App\Models\MessageTemplateModel;

/**
 * Admin CRUD for the example messages offered under the message box on the
 * booking form. Templates may contain {recipient} and {sender}; the booking
 * form fills those from what the visitor has typed. Deactivate rather than
 * delete, like every other reference list here.
 */
class MessageTemplateController extends BaseAdminController
{
    protected MessageTemplateModel $templateModel;
    protected CategoryModel $categoryModel;

    public function __construct()
    {
        $this->templateModel = new MessageTemplateModel();
        $this->categoryModel = new CategoryModel();
    }

    public function index()
    {
        return ui_view('admin/message_templates/index', [
            'templates' => $this->templateModel->adminList(),
        ]);
    }

    public function create()
    {
        return ui_view('admin/message_templates/form', $this->formData(null));
    }

    public function store()
    {
        $error = $this->problem();

        if ($error !== null) {
            return redirect()->back()->withInput()->with('errors', [$error]);
        }

        if ($this->templateModel->insert($this->collectInput()) === false) {
            return redirect()->back()->withInput()->with('errors', $this->templateModel->errors());
        }

        return redirect()->to(site_url('admin/message-templates'))->with('toastr_success', 'Template created.');
    }

    public function edit(int $id)
    {
        $template = $this->templateModel->find($id);

        if ($template === null) {
            return redirect()->to(site_url('admin/message-templates'))->with('toastr_error', 'Template not found.');
        }

        return ui_view('admin/message_templates/form', $this->formData($template));
    }

    public function update(int $id)
    {
        if ($this->templateModel->find($id) === null) {
            return redirect()->to(site_url('admin/message-templates'))->with('toastr_error', 'Template not found.');
        }

        $error = $this->problem();

        if ($error !== null) {
            return redirect()->back()->withInput()->with('errors', [$error]);
        }

        if (! $this->templateModel->update($id, $this->collectInput())) {
            return redirect()->back()->withInput()->with('errors', $this->templateModel->errors());
        }

        return redirect()->to(site_url('admin/message-templates'))->with('toastr_success', 'Template updated.');
    }

    public function deactivate(int $id)
    {
        $ok = $this->templateModel->update($id, ['is_active' => 0]);

        return redirect()->back()->with(
            $ok ? 'toastr_success' : 'toastr_error',
            $ok ? 'Template hidden from the booking form.' : 'Could not deactivate this template.'
        );
    }

    public function activate(int $id)
    {
        $ok = $this->templateModel->update($id, ['is_active' => 1]);

        return redirect()->back()->with(
            $ok ? 'toastr_success' : 'toastr_error',
            $ok ? 'Template shown on the booking form.' : 'Could not activate this template.'
        );
    }

    protected function formData(?array $template): array
    {
        return [
            'template'   => $template,
            'categories' => $this->categoryModel->orderBy('name', 'ASC')->findAll(),
            'languages'  => $this->templateModel->languages(),
            'maxLength'  => config('CbsRadio')->messageMaxLength,
            'tokens'     => MessageTemplateModel::TOKENS,
        ];
    }

    /**
     * What is wrong with the submitted form, in a sentence, or null.
     * A template longer than the booking form allows could never be sent,
     * and an unknown {placeholder} would go to the visitor's message verbatim.
     */
    protected function problem(): ?string
    {
        $rules = [
            'title'    => 'required|max_length[80]',
            'language' => 'required|max_length[40]',
            'body'     => 'required',
        ];

        if (! $this->validate($rules)) {
            return implode(' ', $this->validator->getErrors());
        }

        $body = $this->normalisedBody();
        $max  = config('CbsRadio')->messageMaxLength;

        // Measured with the placeholders still in, which is the worst case for
        // a short name; a long name can still push a message over, and the
        // booking form's own counter will say so.
        if (mb_strlen($body) > $max) {
            return "The message is " . mb_strlen($body) . " characters; the booking form allows at most {$max}.";
        }

        preg_match_all('/\{([^{}]*)\}/', $body, $matches);
        $unknown = array_values(array_diff(array_unique($matches[1]), MessageTemplateModel::TOKENS));

        if ($unknown !== []) {
            return 'The message uses {' . $unknown[0] . '}, which is not a known placeholder. You can use: {'
                . implode('}, {', MessageTemplateModel::TOKENS) . '}.';
        }

        return null;
    }

    protected function normalisedBody(): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", (string) $this->request->getPost('body')));
    }

    protected function collectInput(): array
    {
        $category = $this->request->getPost('category_id');

        return [
            'category_id' => ctype_digit((string) $category) && (int) $category > 0 ? (int) $category : null,
            'language'    => trim((string) $this->request->getPost('language')),
            'title'       => trim((string) $this->request->getPost('title')),
            'body'        => $this->normalisedBody(),
            'sort_order'  => max((int) $this->request->getPost('sort_order'), 0),
            'is_active'   => $this->request->getPost('is_active') ? 1 : 0,
        ];
    }
}
