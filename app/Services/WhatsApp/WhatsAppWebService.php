<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Runs the QR-code companion service (whatsapp-web/server.mjs) from the platform itself:
 * choosing "QR code" in the settings starts it (npm install on the first run, then node server.mjs),
 * choosing another channel stops it, and the scheduler starts it again after a server reboot.
 *
 * The process is detached from the web request and writes to storage/logs/whatsapp-web.log.
 * Node is looked up in WHATSAPP_WEB_NODE, then in the usual install folders, then in the PATH.
 */
class WhatsAppWebService
{
    /** A start in progress (npm install can take a few minutes): do not launch a second one. */
    private const BOOT_SECONDS = 240;

    public function dir(): string
    {
        return base_path("whatsapp-web");
    }

    public function logPath(): string
    {
        return storage_path("logs/whatsapp-web.log");
    }

    public function pidPath(): string
    {
        return storage_path("app/whatsapp-web.pid");
    }

    private function bootPath(): string
    {
        return storage_path("app/whatsapp-web.starting");
    }

    public function enabled(): bool
    {
        return (bool) config("whatsapp.web.autostart", true);
    }

    /** Something answers on the service port (even "no secret yet" means the process is up). */
    public function isRunning(): bool
    {
        $url = parse_url((string) config("whatsapp.web.url", "http://127.0.0.1:3010"));
        $socket = @fsockopen($url["host"] ?? "127.0.0.1", (int) ($url["port"] ?? 3010), $errno, $error, 0.5);

        if ($socket) {
            fclose($socket);

            return true;
        }

        return false;
    }

    /** Started a moment ago and still installing / booting. */
    public function isBooting(): bool
    {
        return is_file($this->bootPath()) && time() - (int) file_get_contents($this->bootPath()) < self::BOOT_SECONDS;
    }

    /**
     * Starts the service unless it is already up or booting.
     *
     * @return string "running", "booting", "started" or "failed"
     */
    public function start(): string
    {
        if (app()->runningUnitTests()) {
            return "failed"; // tests never launch processes (they use a mock of this class)
        }
        if ($this->isRunning()) {
            return "running";
        }
        if ($this->isBooting()) {
            return "booting";
        }
        if (!function_exists("proc_open")) {
            $this->log("proc_open is disabled in php.ini: start the service by hand.");

            return "failed";
        }
        if (!is_file($this->dir() . "/server.mjs")) {
            $this->log("whatsapp-web/server.mjs not found.");

            return "failed";
        }

        [$node, $npm] = $this->binaries();
        File::ensureDirectoryExists(dirname($this->logPath()));
        File::ensureDirectoryExists(dirname($this->bootPath()));
        file_put_contents($this->bootPath(), (string) time());
        $this->log("Starting with node: {$node}");

        $installed = is_dir($this->dir() . "/node_modules/@whiskeysockets/baileys");
        $install = "install --omit=dev --no-audit --no-fund";

        if (PHP_OS_FAMILY === "Windows") {
            // A small batch file, opened by PowerShell's Start-Process: the service gets no handle of
            // this PHP process (a child created by PHP would inherit them and keep the request open)
            // and runs hidden. Only PowerShell inherits them, and it ends right after launching.
            $q = fn (string $s) => '"' . str_replace(['"', "%"], ["", "%%"], $s) . '"';
            $log = $q($this->logPath());
            $batch = storage_path("app/whatsapp-web-start.cmd");
            file_put_contents($batch, implode("\r\n", array_filter([
                "@echo off",
                "cd /d " . $q($this->dir()),
                $installed ? null : "call " . $q($npm) . " {$install} >> {$log} 2>&1",
                $q($node) . " server.mjs >> {$log} 2>&1",
            ])) . "\r\n");
            $command = [
                "powershell.exe", "-NoProfile", "-NonInteractive", "-ExecutionPolicy", "Bypass", "-Command",
                "Start-Process -FilePath '" . str_replace("'", "''", $batch) . "' -WindowStyle Hidden",
            ];
            $null = "NUL";
        } else {
            $q = fn (string $s) => escapeshellarg($s);
            $script = "cd " . $q($this->dir())
                . ($installed ? "" : " && " . $q($npm) . " {$install}")
                . " && exec " . $q($node) . " server.mjs";
            // nohup + & : the service outlives the PHP worker that started it.
            $command = "nohup sh -c " . escapeshellarg($script) . " < /dev/null >> " . escapeshellarg($this->logPath()) . " 2>&1 &";
            $null = "/dev/null";
        }

        $process = @proc_open($command, [
            0 => ["file", $null, "r"],
            1 => ["file", $this->logPath(), "a"],
            2 => ["file", $this->logPath(), "a"],
        ], $pipes, $this->dir());

        // Waits only for the launcher (PowerShell / sh), which returns as soon as the service is spawned.
        if (!is_resource($process) || proc_close($process) !== 0) {
            @unlink($this->bootPath());
            $this->log("Could not launch the process.");

            return "failed";
        }

        return "started";
    }

    /** Stops the service (when another channel is chosen). */
    public function stop(): void
    {
        @unlink($this->bootPath());
        $pid = is_file($this->pidPath()) ? (int) file_get_contents($this->pidPath()) : 0;

        if ($pid <= 0) {
            return;
        }

        if (PHP_OS_FAMILY === "Windows") {
            @exec("taskkill /PID {$pid} /T /F 2>NUL");
        } elseif (function_exists("posix_kill")) {
            @posix_kill($pid, 15);
        } else {
            @exec("kill {$pid} 2>/dev/null");
        }

        @unlink($this->pidPath());
        $this->log("Stopped (pid {$pid}).");
    }

    /** Last lines of the service log, shown on the settings page while it is not connected. */
    public function logTail(int $lines = 15): string
    {
        if (!is_file($this->logPath())) {
            return "";
        }

        $size = (int) @filesize($this->logPath());
        $handle = @fopen($this->logPath(), "r");
        if ($handle === false) {
            return "";
        }
        fseek($handle, max(0, $size - 8192));
        $text = (string) stream_get_contents($handle);
        fclose($handle);

        return implode("\n", array_slice(preg_split("/\r?\n/", trim($text)), -$lines));
    }

    /** [node, npm]: WHATSAPP_WEB_NODE, the usual install folders, or the PATH. */
    public function binaries(): array
    {
        $windows = PHP_OS_FAMILY === "Windows";
        $configured = (string) config("whatsapp.web.node");
        $candidates = array_filter([
            $configured,
            ...($windows
                ? [getenv("ProgramFiles") . "\\nodejs\\node.exe", "C:\\Program Files\\nodejs\\node.exe", getenv("ProgramFiles(x86)") . "\\nodejs\\node.exe"]
                : ["/usr/local/bin/node", "/usr/bin/node", "/opt/homebrew/bin/node"]),
        ]);

        $node = collect($candidates)->first(fn ($path) => is_file($path)) ?? ($windows ? "node.exe" : "node");
        $npmName = $windows ? "npm.cmd" : "npm";
        $npm = str_contains($node, DIRECTORY_SEPARATOR) || str_contains($node, "/")
            ? dirname($node) . DIRECTORY_SEPARATOR . $npmName
            : $npmName;

        return [$node, is_file($npm) ? $npm : $npmName];
    }

    private function log(string $line): void
    {
        // On Windows the running service keeps its log locked for writing: use the Laravel log then.
        File::ensureDirectoryExists(dirname($this->logPath()));
        if (@file_put_contents($this->logPath(), "[" . now()->format("Y-m-d H:i:s") . "] [platform] {$line}\n", FILE_APPEND) === false) {
            Log::info("[whatsapp-web] {$line}");
        }
    }
}
