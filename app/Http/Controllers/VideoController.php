<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

    public function upload(Request $request)
    {
        if ($request->file('file')) {
            $name = $request->file('file')->getClientOriginalName();
            $ext = $request->file('file')->getClientOriginalExtension();
            $mime = $request->file('file')->getClientMimeType();
            $array = explode(".", $name);
            if (end($array) === "mp4") {
                $this->run();
            } else {
                $file = Storage::disk('b2')->put($name, $request->file('file'));
                $uri = 'https://f000.backblazeb2.com/file/video-app/' . $file;
            }
            return response()->json(['success' => true,
                'data' => [
                    'type_id' => $this->getTypeByMime($mime),
                    'name' => $name,
                    'path' => $uri,
                    'extension' => $ext
                ]
            ]);
        } else {
            return response()->json(['success' => false]);
        }

    }

    public function run($pathToFile, $personalBucket = null): ?bool
    {
        try {
            $this->setDriver();
            $this->initialize($pathToFile, $personalBucket);
            Log::warning("Operation for $personalBucket __ $pathToFile successful!");
            return $this->convert(self::getRandomString(12), false, $personalBucket);
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

    private function convert($saveTo = null, $saveLocal = false, $personalBucket = null): string
    {

//        if ($saveLocal === true) {
//            $url = env('APP_URL') . '/storage/' . $saveTo;
        $saveTo = storage_path('app/public/' . $saveTo);
//
//        } else {
//        $dest = 's3://' . env('VIDEO_SAVE_BUCKET') . '/' . $saveTo;
//        $filename = 'original.m3u8';
//        $to_s3 = [
//            'cloud' => $this->bucket,
//            'options' => [
//                'dest' => $dest,
//                'filename' => ''
//            ]
//        ];
//        $url = $dest . '/' . $filename;
//        }

        $r_480p = (new Representation)->setKiloBitrate(400)->setResize(854, 480);

        $this->video->hls()
            ->x264()
            ->fragmentedMP4()
//            ->setHlsListSize(5)
            ->setFlags([HLSFlag::DELETE_SEGMENTS])
            ->setHlsTime(30)
            ->setHlsAllowCache(false)
            ->addRepresentations([$r_480p])
            ->save($saveTo);
//        echo "\n-----\n";
//        echo "Url: " . $url;
//        echo "\n-----\n";
        return $saveTo;
    }

    private function getTypeByMime($mime)
    {
        if (strpos($mime, 'audio') !== false) {
            return 5;
        }
        if (strpos($mime, 'video') !== false) {
            return 3;
        }
        if (strpos($mime, 'document') !== false) {
            return 4;
        }
        if (strpos($mime, 'pdf') !== false) {
            return 4;
        }
        if (strpos($mime, 'image') !== false) {
            return 2;
        }
        return 1;
    }
}
