<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Wikisource Google OCR</title>
        <link rel="stylesheet" href="//tools-static.wmflabs.org/cdnjs/ajax/libs/twitter-bootstrap/3.3.6/css/bootstrap.min.css" />
    </head>
    <body>
        <div class="container">
            <div class="page-header">
                <h1>Wikisource Google OCR</h1>
            </div>

            <form action="api.php" method="get">
                <div class="form-group">
                    <label for="image" class="form-label">Image URL:</label>
                    <input type="text" name="image" class="form-control" />
                </div>
                <div class="form-group">
                    <label for="lang" class="form-label">Language code (optional):</label>
                    <input type="text" name="lang" class="form-control" />
                </div>
                <div class="form-group">
                    <input type="submit" value="Go" class="btn btn-info" />
                </div>
            </form>

            <p class="text-muted text-right">
                Please report any bugs or feature requests via
                <a href="https://phabricator.wikimedia.org">Phabricator</a>
            </p>
        </div>
        <script type="text/javascript" src="//tools-static.wmflabs.org/cdnjs/ajax/libs/jquery/2.2.4/jquery.min.js"></script>
        <script type="text/javascript" src="//tools-static.wmflabs.org/cdnjs/ajax/libs/twitter-bootstrap/3.3.6/js/bootstrap.min.js"></script>
    </body>
</html>
