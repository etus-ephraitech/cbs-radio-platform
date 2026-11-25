<?= $this->extend('layouts/public') ?>

<?= $this->section('content') ?>

<div class="page-header">
    <h1 class="h3 mb-1">Send a Greeting</h1>
    <p class="text-muted mb-0">Pick what you're celebrating, and we'll take it from there.</p>
</div>

<?php if (empty($categories)): ?>
    <div class="alert alert-secondary">No categories are open for booking right now — please check back shortly.</div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($categories as $category): ?>
            <div class="col-md-6 col-lg-4">
                <a href="<?= site_url('booking/' . $category['slug']) ?>" class="text-decoration-none">
                    <div class="card category-card h-100 shadow-sm">
                        <div class="card-body">
                            <h2 class="h5 mb-2 text-dark"><?= esc($category['name']) ?></h2>
                            <p class="text-muted small mb-0"><?= esc(cbs_excerpt($category['description'] ?? '', 90)) ?></p>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
