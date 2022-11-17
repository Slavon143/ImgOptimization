<!doctype html>

<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
    <title>Image optimization report</title>
</head>
<body>
<div class="container" style="max-width: 900px;">
    <h2>Image optimization report</h2>
    <dl class="row">
        <dt class="col-sm-3">Total:</dt>
        <dd class="col-sm-9">
            <?php
            $dbh = new PDO('mysql:dbname=optimize;host=localhost', 'root', '');
            $sth = $dbh->prepare("SELECT COUNT(`id`) FROM `optimize_img`");
            $sth->execute();
            echo $sth->fetch(PDO::FETCH_COLUMN);
            ?> Things
        </dd>
        <dt class="col-sm-3">Completed:</dt>
        <dd class="col-sm-9">
            <?php
            $sth = $dbh->prepare("SELECT COUNT(`id`) FROM `optimize_img` WHERE `done` = 1");
            $sth->execute();
            echo $sth->fetch(PDO::FETCH_COLUMN);
            ?> Things
        </dd>
        <dt class="col-sm-3">Optimized:</dt>
        <dd class="col-sm-9">
            <?php
            $sth = $dbh->prepare("SELECT SUM(`diff`) FROM `optimize_img` WHERE `done` = 1");
            $sth->execute();
            echo round($sth->fetch(PDO::FETCH_COLUMN) / 1024 / 1024, 2);
            ?> МБ
        </dd>
        <dt class="col-sm-3">Errors:</dt>
        <dd class="col-sm-9">
            <?php
            $sth = $dbh->prepare("SELECT COUNT(`id`) FROM `optimize_img` WHERE `error` <> ''");
            $sth->execute();
            echo $sth->fetch(PDO::FETCH_COLUMN);
            ?>
            Things
        </dd>
    </dl>
    <?php
    $sth = $dbh->prepare("SELECT * FROM `optimize_img` WHERE `error` <> ''");
    $sth->execute();
    $errors = $sth->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($errors)) {
        foreach ($errors as $error) {
            ?>
            <div class="alert alert-danger" role="alert">
                <strong><?php echo $error['img']; ?></strong>
                <br><?php echo $error['error']; ?>
            </div>
            <?php
        }
    }
    ?>
    <form method="post" name="clean">
        <input type="submit" name="clean" value="Clean DB">
    </form>
</div>


<?php
if (isset($_POST['clean'])){
    $dbh->prepare("TRUNCATE TABLE `optimize`.`optimize_img`")->execute();
    header("Refresh: 0");
}
?>
</body>
</html>