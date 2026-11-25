<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($pageTitle) ? esc($pageTitle) . ' — CBS Radio' : 'CBS Radio' ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.css" rel="stylesheet">

    <style>
        :root {
            --cbs-navy: #101B33;
            --cbs-navy-deep: #0A1226;
            --cbs-on-air: #D62828;
            --cbs-gold: #F4A300;
            --cbs-paper: #F5F6F8;
            --cbs-ink: #1B1B1F;
        }

        body {
            background: var(--cbs-paper);
            color: var(--cbs-ink);
        }

        h1, h2, h3, h4, .brand-font {
            font-family: 'Space Grotesk', system-ui, sans-serif;
        }

        .navbar-cbs {
            background: var(--cbs-navy);
        }

        .navbar-cbs .navbar-brand {
            font-family: 'Space Grotesk', system-ui, sans-serif;
            font-weight: 700;
            color: #fff;
            letter-spacing: 0.01em;
        }

        .on-air-tag {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            background: var(--cbs-on-air);
            color: #fff;
            font-size: .7rem;
            font-weight: 700;
            padding: .15rem .5rem;
            border-radius: 3px;
            letter-spacing: .04em;
        }

        .on-air-tag::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #fff;
        }

        .navbar-cbs .nav-link {
            color: rgba(255,255,255,.75);
        }

        .navbar-cbs .nav-link:hover,
        .navbar-cbs .nav-link.active {
            color: #fff;
        }

        .category-card {
            border: none;
            border-left: 4px solid var(--cbs-gold);
            background: #fff;
            border-radius: 6px;
            transition: transform .15s ease;
        }

        .category-card:hover {
            transform: translateY(-2px);
        }

        .btn-cbs-primary {
            background: var(--cbs-navy);
            border-color: var(--cbs-navy);
            color: #fff;
        }

        .btn-cbs-primary:hover {
            background: var(--cbs-navy-deep);
            border-color: var(--cbs-navy-deep);
            color: #fff;
        }

        .page-header {
            border-bottom: 1px solid rgba(0,0,0,.08);
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
        }
    </style>

    <?= $this->renderSection('head') ?>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-cbs navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="<?= site_url('/') ?>">CBS Radio <span class="on-air-tag">ON AIR</span></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#cbsNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="cbsNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="<?= site_url('booking') ?>">Send a Greeting</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= site_url('gifts') ?>">Send a Gift</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= site_url('presenter') ?>">Presenter</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= site_url('admin') ?>">Admin</a></li>
            </ul>
        </div>
    </div>
</nav>

<main class="container py-4">
    <?= $this->renderSection('content') ?>
</main>

<footer class="text-center text-muted small py-4">
    &copy; <?= date('Y') ?> CBS Radio. Built by Ephraitech Unified Solutions.
</footer>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.10.1/sweetalert2.all.min.js"></script>

<?= view_cell('App\Cells\ToastrFlash::render') ?>

<?= $this->renderSection('scripts') ?>
</body>
</html>
