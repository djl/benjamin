<?php
//
// And Jacob begat Benjamin
// http://github.com/djl/benjamin
//

// The S3 bucket to list
// This bucket *must* have anonymous list/read permissions
define('BUCKET', 'example');

// The base URL to serve content from (e.g. http://example.com/)
// Default is the standard S3 URL (e.g. mybucket.s3.amazonaws.com)
define('BASE', '//' . BUCKET . '.s3.amazonaws.com/');

// known image extensions, case-insensitive
$img_extensions = array('bmp', 'gif', 'png', 'jpe?g');

// number of columns
$columns = 4;

// max image width
// set this to a non-integer value to disable
$max_image_width = 640;

function hfilesize($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    $precision = ($pow <= 1) ? 0 : $precision;
    return round($bytes, $precision) . $units[$pow];
}

function get_files($dir, $img_extensions) {
    $files = array('images' => array());
    $pattern = sprintf("/\.%s$/i", implode("|", $img_extensions));

    $ch = curl_init();
    curl_setopt_array($ch, array(CURLOPT_CONNECTTIMEOUT => 60,
                                 CURLOPT_FAILONERROR => true,
                                 CURLOPT_HEADER => false,
                                 CURLOPT_RETURNTRANSFER => 1,
                                 CURLOPT_TIMEOUT => 60,
                                 CURLOPT_URL => 'http://' . BUCKET . '.s3.amazonaws.com/'));
    $data = curl_exec($ch);
    if (curl_error($ch)) {
        die(curl_error($ch));
    }
    $xml = new SimpleXMLElement($data);
    curl_close($ch);

    foreach ($xml->Contents as $object) {
        $pile = preg_match($pattern, $object->Key) ? "images" : "other";
        $files[$pile][(string)$object->Key] = hfilesize((int)$object->Size);
        ksort($files[$pile]);
    }
    return $files;
}

$files = get_files(BUCKET, $img_extensions);
$current = null;
if (isset($_GET['img'])) {
    if (array_key_exists($_GET['img'], $files['images'])) {
        $current = $_GET['img'];
    }
} else {
    if (count($files['images']) > 0) {
        reset($files['images']);
        $current = key($files['images']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Browsing <?php echo dirname($_SERVER['PHP_SELF']) ?></title>
    <style type="text/css">
        *{margin:0;padding:0;}
        body{background:white;color:black;font:11px/16px Verdana,"Bitstream Vera Sans",sans-serif;padding:30px;}
        h1,h2,h3{font-size:inherit;}
        h1,h3{background:black;color:white;margin:-30px -30px 30px -30px;padding:10px;}
        h2{font-size:14px;}
        h3{display:inline;padding:10px 10px 10px 35px;}
        a:link,a:visited{color:black;}
        a:hover,a:active,a:focus{color:#f60;}
        table{margin:10px 0 30px;}
        td{vertical-align:top;}
        td.light{color:#999;}
        img{display:block;margin:30px 0 50px;<?php if(is_int($max_image_width)): ?>max-width:<?php echo $max_image_width; ?>px;<?php endif; ?>}
    </style>
</head>
<body>
    <h1>Browsing <?php echo dirname($_SERVER['PHP_SELF']) ?></h1>
    <?php if (!is_null($current)): ?><h2><?php echo $current ?></h2><img src="<?php echo BASE ?><?php echo $current ?>"><?php endif;?>
    <?php foreach ($files as $group => $groupfiles): ?>
        <?php if(count($groupfiles) == 0) continue; ?>
        <?php $open = false; $pos = 1; ?>
        <?php $filecount = count($groupfiles); ?>
        <?php $files_per_column = ceil($filecount / $columns); ?>
        <?php if ($filecount <= $columns) $files_per_column = $filecount; ?>
        <h3><?php echo $group; ?></h3>
        <table><tr>
        <?php foreach($groupfiles as $name => $size): ?>
            <?php $is_img = array_key_exists($name, $files['images']);?>
            <?php if (!$open): $open = true; ?><td><table><?php endif; ?>
            <?php if ($pos > $files_per_column): $open = true; $pos = 1; ?></table></td><td><table><?php endif; ?>
            <tr><td><a href="<?php if($is_img) { echo $_SERVER['PHP_SELF'] . '?img='; } else { echo BASE ; } ?><?php echo $name ?>"><?php echo $name; ?><?php if($is_img): ?><a href="<?php echo $_SERVER['PHP_SELF'] ?>?img=<?php echo $name ?>"></a><?php endif; ?></td><td class="light"><?php if(!is_null($size)): ?>(<?php echo $size; ?>)<?php endif; ?></td></tr>
            <?php $pos++; ?>
        <?php endforeach; ?>
        <?php if ($open): ?></td></table><?php endif; ?>
        </tr></table>
    <?php endforeach; ?>
</body>
</html>
