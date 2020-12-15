<?php


namespace App\Http\Controllers;


use Illuminate\Support\Facades\Log;

class VideoController extends Controller
{
    private const chunkSize = 1024000 * 30;

    private $path = "";
    private $type = "";
    private $stream = "";
    private $buffer = 10240000;
    private $start = -1;
    private $end = -1;
    private $chunkStart = -1;
    private $chunkEnd = -1;
    private $size = 0;

    public function __construct($filePath, $type, $chunkStart = null, $chunkEnd = null)
    {
        $this->path = $filePath;
        $this->type = $type;
        $this->chunkStart = $chunkStart + 1;
        $this->chunkEnd = $chunkEnd;
    }

    /**
     * Calc chunks
     */
    public function calculateChunks(): object
    {
        $size = filesize($this->path);
        $chunks = [];
        $chunkIndex = 0;
        for ($chunk = self::chunkSize; $chunk <= $size; $chunk += self::chunkSize) {
            $chunks[$chunkIndex] = $chunk;
            $chunkIndex++;
        }
        $chunks[$chunkIndex] = $size;
        return (object)$chunks;
    }

    /**
     * Run streaming
     */
    public function run(): void
    {
        $this->open();
        $this->setHeader();
        $this->executeStream();
        $this->close();
    }

    /**
     * Open stream
     */
    private function open(): void
    {
        if (!($this->stream = fopen($this->path, 'rb'))) {
            die('Could not open stream for reading');
        }
    }

    /**
     * Set Headers
     */
    private function setHeader(): void
    {
        ob_get_clean();
        header("Content-Type: video/" . $this->type);
        //     header("Cache-Control: max-age=2592000, public");
        header("Expires: " . gmdate('D, d M Y H:i:s', time() + 2592000) . ' GMT');
        header("Last-Modified: " . gmdate('D, d M Y H:i:s', @filemtime($this->path)) . ' GMT');
        $this->start = $this->chunkStart ?? 0;
        $this->size = $this->chunkEnd ?? filesize($this->path);
        $this->end = $this->chunkEnd ?? $this->size - 1;
        header("Accept-Ranges: 0-" . $this->end);

        if (isset($_SERVER['HTTP_RANGE'])) {
            $c_start = $this->start;
            $c_end = $this->end;

            list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
            if (strpos($range, ',') !== false) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes $this->start-$this->end/$this->size");
                exit;
            }
            if ($range == '-') {
                $c_start = $this->size - substr($range, 1);
            } else {
                $range = explode('-', $range);
                $c_start = $range[0];

                $c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $c_end;
            }
            $c_end = ($c_end > $this->end) ? $this->end : $c_end;
            if ($c_start > $c_end || $c_start > $this->size - 1 || $c_end >= $this->size) {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                header("Content-Range: bytes $this->start-$this->end/$this->size");
                exit;
            }
            $this->start = $c_start;
            $this->end = $c_end;
            $length =
                $this->chunkStart !== null && $this->chunkEnd !== null ?
                    $this->chunkEnd - $this->chunkStart + 1 :
                    $this->end - $this->start + 1;
            fseek($this->stream, $this->start);
            header('HTTP/1.1 206 Partial Content');
            header("Content-Length: " . $length);
            header("Content-Range: bytes $this->start-$this->end/" . $this->size);
        } else {
            $length =
                $this->chunkStart !== null && $this->chunkEnd !== null ?
                    $this->chunkEnd - $this->chunkStart + 1 :
                    $this->size;
            header("Content-Length: " . $length);
            header("Accept-Ranges: bytes");
        }

    }

    /**
     * Execute stream
     */
    private function executeStream(): void
    {
        $i = $this->chunkStart ?? $this->start;
        $end = $this->chunkEnd ?? $this->end;
        set_time_limit(0);
        Log::info($i . ' bytes start');
        while ($i <= $end) {


            $bytesToRead = $this->buffer;
//            if (($i + $bytesToRead) > $end) {
//                $bytesToRead = $end - $i + 1;
//
//            }

            $data = fread($this->stream, $bytesToRead);
            Log::info($bytesToRead . ' bytes sent');
            echo $data;
            flush();
            $i += $bytesToRead;
        }
        Log::info($end . ' bytes end');
    }

    /**
     * Close stream
     */
    private function close(): void
    {
        fclose($this->stream);
        exit;
    }
}
