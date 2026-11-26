<?php
/**
 * <head> of the v1 admin layout: meta, the theme's stylesheets, and the two
 * scripts the theme needs before the body renders (main.js restores the
 * saved colour mode and menu style, choices.js is used by custom.js).
 *
 * Every asset goes through base_url(), so the admin works below a
 * sub-folder and at any URL depth. window.CBS_ASSETS tells the theme's
 * scripts where the assets live (they swap in the RTL Bootstrap sheet).
 *
 * @var string $documentTitle Set by layouts/admin.php
 */
$asset = static fn (string $path): string => esc(base_url('assets/' . ltrim($path, '/')), 'attr');
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="vertical" data-theme-mode="light" data-header-styles="light" data-width="fullwidth" data-menu-styles="dark" data-toggled="close">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="robots" content="noindex, nofollow">
    <meta name="author" content="Ephraitech Unified Solutions">
    <?= csrf_meta() ?>
    <title><?= esc($documentTitle) ?></title>

    <?php $brandFav = cbs_brand()['favicon']; ?>
    <?php if ($brandFav !== ''): ?><link rel="icon" href="<?= esc($brandFav, 'attr') ?>">
    <?php else: ?><link rel="icon" href="<?= $asset('images/brand-logos/toggle-logo.svg') ?>" type="image/svg+xml"><?php endif; ?>

    <script>window.CBS_ASSETS = <?= json_encode(base_url('assets/'), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script>
    <script src="<?= $asset('libs/choices.js/public/assets/scripts/choices.min.js') ?>"></script>
    <script src="<?= $asset('js/main.js') ?>"></script>

    <link id="style" href="<?= $asset('libs/bootstrap/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= $asset('css/styles.min.css') ?>" rel="stylesheet">
    <link href="<?= $asset('css/icons.min.css') ?>" rel="stylesheet">
    <link href="<?= $asset('libs/node-waves/waves.min.css') ?>" rel="stylesheet">
    <link href="<?= $asset('libs/simplebar/simplebar.min.css') ?>" rel="stylesheet">
    <link href="<?= $asset('libs/flatpickr/flatpickr.min.css') ?>" rel="stylesheet">
    <link href="<?= $asset('libs/@simonwep/pickr/themes/nano.min.css') ?>" rel="stylesheet">
    <link href="<?= $asset('libs/choices.js/public/assets/styles/choices.min.css') ?>" rel="stylesheet">
    <link href="<?= $asset('libs/sweetalert2/sweetalert2.min.css') ?>" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.css" rel="stylesheet">
    <link href="<?= $asset('css/admin.css') ?>" rel="stylesheet">
