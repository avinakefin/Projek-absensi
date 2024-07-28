<!DOCTYPE html>
<html lang="en">
<head>
    <?= $this->include('templates/head') ?>
    <title><?= esc($title) ?></title>
</head>
<body>
    <?= $this->include('templates/navbar') ?>
    <div class="container">
        <h2><?= esc($title) ?></h2>
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success">
                <?= session()->getFlashdata('success') ?>
            </div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger">
                <?= session()->getFlashdata('error') ?>
            </div>
        <?php endif; ?>
        <form action="<?= base_url('admin/import-guru/import') ?>" method="post">
            <div class="form-group">
                <label for="import-data">Data Guru</label>
                <textarea name="import_data" id="import-data" class="form-control" rows="10" placeholder="Masukkan data Excel disini."></textarea>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Import</button>
                <a href="<?= base_url('public/import/import-guru.xls') ?>" class="btn btn-primary">Download Format</a>
                <a href="<?= base_url('admin/general-settings') ?>" class="btn btn-secondary">Kembali</a>
            </div>
        </form>
    </div>
    <?= $this->include('templates/footer') ?>
</body>
</html>
