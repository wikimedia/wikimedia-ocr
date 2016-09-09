<?php
require __DIR__ . '/vendor/autoload.php';

// Optical Character Recognition.
$ocr = new Wikisource\GoogleOcr\Ocr(__DIR__, $_GET);

// Localisation.
$i18n = new Intuition('ws-google-ocr');
$i18n->registerDomain('ws-google-ocr', __DIR__ . '/messages');

?><!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title><?php echo $i18n->msg('title') ?></title>
        <link rel="stylesheet" href="//tools-static.wmflabs.org/cdnjs/ajax/libs/twitter-bootstrap/3.3.6/css/bootstrap.min.css" />
    </head>
    <body>
        <div class="container">
            <div class="page-header">
                <h1><?php echo $i18n->msg('title') ?></h1>
            </div>

            <form action="index.php" method="get">
                <div class="form-group">
                    <label for="image" class="form-label"><?php echo $i18n->msg('image-url') ?></label>
                    <input type="text" name="image" class="form-control" value="<?php echo htmlspecialchars($ocr->getImage()) ?>" />
                </div>
                <div class="form-group">
                    <label for="lang" class="form-label"><?php echo $i18n->msg('language-code') ?></label>
                    <input type="text" name="lang" class="form-control" value="<?php echo htmlspecialchars($ocr->getLang()) ?>" />
                </div>
                <div class="form-group">
                    <input type="submit" value="<?php echo $i18n->msg('submit') ?>" class="btn btn-info" />
                </div>
            </form>

            <?php if ($ocr->hasValidImage()): ?>
            <div class="row">
                <div class="col-md-6">
                    <img class="img-responsive" src="<?php echo htmlspecialchars($ocr->getImage()) ?>" alt="The original image" />
                </div>
                <div class="col-md-6">
                    <?php try { $text = $ocr->getText(); } catch (\Exception $e) { ?>
                    <div class="alert alert-danger" role="alert"><?php echo $e->getMessage() ?></div>
                    <?php } ?>
                    <?php if (isset($text)): ?>
                    <textarea class="form-control" rows="<?php echo max(10, substr_count($text, "\n")) ?>"><?php echo $text ?></textarea>
                    <p class="help-block"><?php echo $i18n->msg('textarea-help') ?></p>
                    <?php endif ?>
                </div>
            </div>
            <?php endif ?>

            <hr />
            <p>
                <?php echo $i18n->msg('more-info') ?>
                <a href="https://wikisource.org/wiki/Wikisource:Google_OCR">Wikisource:Google OCR</a>
            </p>
        </div>
        <script type="text/javascript" src="//tools-static.wmflabs.org/cdnjs/ajax/libs/jquery/2.2.4/jquery.min.js"></script>
        <script type="text/javascript" src="//tools-static.wmflabs.org/cdnjs/ajax/libs/twitter-bootstrap/3.3.6/js/bootstrap.min.js"></script>
    </body>
</html>
