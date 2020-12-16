<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Streaming;
use Streaming\FFMpeg;
use Streaming\HLSFlag;
use Throwable;

class VideoController extends Controller
{

    private $ffmpeg;
    private $video;

    public function run($pathToFile, $personalBucket = null): ?bool
    {
        try {
            $this->setDriver();
            $this->initialize($personalBucket, $pathToFile);
            $this->convert(self::getRandomString() . '/' . self::getRandomString(), true);
            Log::warning("Operation for $personalBucket __ $pathToFile successful!");
            return true;
        } catch (Throwable $th) {
            Log::warning($th);
            return false;
        }

    }

    private function setDriver(): void
    {
        $conversionConfig = [
            'ffmpeg.binaries' => '/usr/bin/ffmpeg',
            'ffprobe.binaries' => '/usr/bin/ffprobe',
            'timeout' => 3600,
            'ffmpeg.threads' => 24,
        ];
        $this->ffmpeg = FFMpeg::create($conversionConfig);
    }

    private function initialize($pathToFile, $personalBucket = null): void
    {
        if ($personalBucket) {
            $bucketConfig = [
                'version' => 'latest',
                'region' => env('VIDEO_BUCKET_REGION'),
                'credentials' => [
                    'key' => env('VIDEO_BUCKET_ACCESS_KEY'),
                    'secret' => env('VIDEO_BUCKET_SECRET_KEY'),
                ]
            ];
            $bucket = new Streaming\Clouds\S3($bucketConfig);
            $fileConfig = [
                'cloud' => $bucket,
                'options' => [
                    'Bucket' => $personalBucket,
                    'Key' => $pathToFile
                ]
            ];
            $this->video = $this->ffmpeg->openFromCloud($fileConfig);
        } else {
            $this->video = $this->ffmpeg->open($pathToFile);
        }

    }

    private function convert($saveTo, $saveLocal = false): void
    {
        if ($saveLocal === true) {
            $url = env('APP_URL') . '/storage/' . $saveTo;
            $saveTo = storage_path('app/public/' . $saveTo);
        } else {
            $url = $saveTo;
        }
        $this->video->hls()
            ->x264()
            ->fragmentedMP4()
//            ->setHlsListSize(5)
            ->setFlags([HLSFlag::DELETE_SEGMENTS])
            ->setHlsTime(30)
            ->setHlsAllowCache(false)
            ->autoGenerateRepresentations()
            ->save($saveTo);
        echo "\n-----\n";
        echo "Url: " . $url;
        echo "\n-----\n";
    }
}
