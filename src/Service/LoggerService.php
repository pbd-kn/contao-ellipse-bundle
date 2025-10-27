<?php

// src/Service/MyLoggerService.php
namespace PbdKn\ContaoEllipseBundle\Service;

use Psr\Log\LoggerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Eigener LoggerService für Contao-Ellipse-Bundle.
 * Schreibt Debug- und Fehlermeldungen in eine eigene Log-Datei im Projekt-Verzeichnis.
 */
class LoggerService
{
    private string $dateiname;
    private LoggerInterface  $contaoLogger;
    private string $projectDir;
    private bool $debug = false;
    // Variablen für späte Initialisierung
    private ?StreamHandler $streamHandler = null;
    
    public function __construct(LoggerInterface $contaoLogger, ParameterBagInterface $params, string $dateiname = 'ellipsedebug.log')
    {
        $this->dateiname = $dateiname;
        $this->contaoLogger = $contaoLogger;
        $this->debug = $params->get('kernel.debug');
        $this->projectDir = $params->get('kernel.project_dir');
    }

    public function debugMe(string $txt): void
    {
        if ($this->debug) {
            if ($this->streamHandler === null) { // Erst wenn der Debug-Modus aktiv ist und noch nicht initialisiert wurde
                $logPath = $this->projectDir . '/var/logs/' . $this->dateiname;
               // Erstelle einen LineFormatter, der nur die Nachricht loggt
                $formatter = new LineFormatter('%datetime% [Logger] %message%'. PHP_EOL, null, true, true);            
                $this->streamHandler = new StreamHandler($logPath, Logger::INFO);
                $this->streamHandler->setFormatter($formatter);  // Setze den benutzerdefinierten Formatter
                $this->contaoLogger->pushHandler($this->streamHandler);

                // Optional: Log-Nachrichten beim ersten Initialisieren
                //$this->contaoLogger->info('Logger initialisiert für ' . $this->dateiname);
            }    
            $this->contaoLogger->info($this->addDebugInfoToText($txt));
        }
    }

public function debugDumpMe(mixed $var, string $txt = ''): void
{
    if ($this->debug) {
        if ($this->streamHandler === null) {
            $logPath = $this->projectDir . '/var/logs/' . $this->dateiname;
            $formatter = new LineFormatter('%datetime% [Logger] %message%' . PHP_EOL, null, true, true);
            $this->streamHandler = new StreamHandler($logPath, Logger::INFO);
            $this->streamHandler->setFormatter($formatter);
            $this->contaoLogger->pushHandler($this->streamHandler);
        }

        // Text hinzufügen (wenn vorhanden)
        $textPart = $txt !== '' ? "Message: $txt" . PHP_EOL : '';
        if ($textPart == '') {
        // 🔍 Backtrace für Ort des Aufrufs
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $caller = $trace[1] ?? $trace[0];
            $file = $caller['file'] ?? 'unknown file';
            $line = $caller['line'] ?? 'unknown line';
        } else {
            $file = '';
            $line = '';
        }
        $dump = print_r($var, true);
        $dump = trim($dump);


        $this->contaoLogger->info( PHP_EOL . "--- DEBUG DUMP AT $file:$line ---" . PHP_EOL . $textPart . $dump . PHP_EOL . "--- DEBUG DUMP END ---" . PHP_EOL );

//        $this->contaoLogger->info(PHP_EOL . "--- DEBUG DUMP $textPart ---" . PHP_EOL . $dump . PHP_EOL . "--- DEBUG DUMP END ---" . PHP_EOL );
    }
}

    public function Error(string $txt): void
    {
        if ($this->streamHandler === null) { // Erst wenn der Debug-Modus aktiv ist und noch nicht initialisiert wurde
            $logPath = $this->projectDir . '/var/logs/' . $this->dateiname;
            // Erstelle einen LineFormatter, der nur die Nachricht loggt
            $formatter = new LineFormatter('%datetime% [Logger] %message%'. PHP_EOL, null, true, true);            
            $this->streamHandler = new StreamHandler($logPath, Logger::INFO);
            $this->streamHandler->setFormatter($formatter);  // Setze den benutzerdefinierten Formatter
            $this->contaoLogger->pushHandler($this->streamHandler);

            // Optional: Log-Nachrichten beim ersten Initialisieren
            //$this->contaoLogger->info('Logger initialisiert für ' . $this->dateiname);
        }    
        $this->contaoLogger->error($this->addDebugInfoToText($txt));
    }
    public function isDebug(): bool
    {
        return $this->debug;
    }
    /*
     * schaltet den debug asuschrieb unabhängig von kernel.debug ein
     */
    public function setDebug()
    {
        $this->debug=true;
        $this->debugMe("debug eingeschaltet");
    }
    /*
     * setzt den debug asuschrieb auf den defaultwert bei contao debuger ein wird eingesaltet.
     */
    public function defaultDebug(): bool
    {
        $this->debug=$container->getParameter('kernel.debug');
        return $this->debug;
    }
    
    /* füege modul funktion und zeile dazu
     *
     */
    private function addDebugInfoToText(string $text): string
    {
        // Hole den aktuellen Stack-Trace und extrahiere Informationen
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $backtrace[1];

        // Extrahiere den Dateinamen und die Zeilennummer
        $file = isset($caller['file']) ? $caller['file'] : 'unknown file';
        $line = isset($caller['line']) ? $caller['line'] : 'unknown line';

        // Extrahiere den Funktionsnamen
        $function = isset($caller['function']) ? $caller['function'] : 'unknown function';

        // Baue den Log-Text mit dem Modulnamen (Dateiname, Zeilennummer und Funktionsname) zusammen
        $logInfo = sprintf('[%s:%d] %s : %s', basename($file), $line, $function, $text);

        // Rückgabe des erweiterten Text
        return $logInfo;
    }

}
