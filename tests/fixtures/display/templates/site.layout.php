<!DOCTYPE html>
<html>
<head><title><?php echo $title ?? 'Layout'; ?></title></head>
<body data-page="<?php echo $page ?? ''; ?>">
<?php echo $header; ?>
<main id="site-content"><?php echo $content; ?></main>
<?php echo $footer; ?>
</body>
</html>
