<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Streaming;
use Streaming\FFMpeg;
use Streaming\HLSFlag;
use Streaming\Representation;
use Throwable;

class VideoController extends Controller
{

    private $ffmpeg;
    private $video;
    private $bucket;

    public function run($pathToFile, $personalBucket = null): ?bool
    {
        try {
            $this->setDriver();
            $this->initialize($pathToFile, $personalBucket);
            $this->convert(self::getRandomString(12), false, $personalBucket);
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
            'timeout' => 7200,
            'ffmpeg.threads' => 8,
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
            $this->bucket = new Streaming\Clouds\S3($bucketConfig);
            $fileConfig = [
                'cloud' => $this->bucket,
                'options' => [
                    'Bucket' => $personalBucket,
                    'Key' => $pathToFile
                ]
            ];
            $this->video = $this->ffmpeg->openFromCloud($fileConfig);
//        } else {
//            $this->video = $this->ffmpeg->open($pathToFile);
        }

    }

    private function convert($saveTo, $saveLocal = false, $personalBucket = null): void
    {

//        if ($saveLocal === true) {
//            $url = env('APP_URL') . '/storage/' . $saveTo;
//            $saveTo = storage_path('app/public/' . $saveTo);
//
//        } else {
            $dest = 's3://' . env('VIDEO_SAVE_BUCKET') . '/' . $saveTo;
            $filename = 'original.m3u8';
            $to_s3 = [
                'cloud' => $this->bucket,
                'options' => [
                    'dest' => $dest,
                    'filename' =>  ''
                ]
            ];
            $url = $dest . '/' . $filename;
//        }

        $r_360p = (new Representation)->setKiloBitrate(276)->setResize(640, 360);
        $r_480p = (new Representation)->setKiloBitrate(400)->setResize(854, 480);
        $r_720p = (new Representation)->setKiloBitrate(2048)->setResize(1280, 720);

        $this->video->hls()
            ->x264()
            ->fragmentedMP4()
//            ->setHlsListSize(5)
            ->setFlags([HLSFlag::DELETE_SEGMENTS])
            ->setHlsTime(120)
            ->setHlsAllowCache(false)
            ->addRepresentations([$r_480p])
            ->save(null, [$to_s3]);
        echo "\n-----\n";
        echo "Url: " . $url;
        echo "\n-----\n";
    }
}
